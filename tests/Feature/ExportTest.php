<?php

use craft\helpers\App;
use craft\utilities\ClearCaches;
use craft\web\Application;
use League\Csv\Bom;
use League\Csv\Reader;
use markhuot\craftpest\web\TestableResponse;
use superbig\beam\Beam;
use superbig\beam\controllers\DefaultController;
use superbig\beam\models\BeamModel;
use superbig\beam\models\Settings;
use superbig\beam\services\BeamService;
use yii\base\ExitException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

function beamPlugin(): Beam
{
    $plugins = Craft::$app->getPlugins();

    if (!$plugins->isPluginInstalled('beam')) {
        $plugins->installPlugin('beam');
    } elseif (!$plugins->isPluginEnabled('beam')) {
        $plugins->enablePlugin('beam');
    }

    $plugin = $plugins->getPlugin('beam');
    expect($plugin)->toBeInstanceOf(Beam::class);
    expect($plugin->beamService)->toBeInstanceOf(BeamService::class);

    return $plugin;
}

function beamResetResponse(): TestableResponse
{
    $response = Craft::createObject(TestableResponse::class);
    Craft::$app->set('response', $response);

    return $response;
}

function beamResetSettings(bool $deleteFilesAfterDownload = false): Settings
{
    $plugin = beamPlugin();
    $settings = $plugin->getSettings();
    $settings->deleteFilesAfterDownload = $deleteFilesAfterDownload;

    return $settings;
}

/**
 * @return array{
 *   response: TestableResponse,
 *   location: string,
 *   hash: string,
 *   config: array{filename: string, tempFilename: string, mimeType: string},
 *   bytes: string
 * }
 */
function beamExport(string $format, BeamModel $model): array
{
    $plugin = beamPlugin();
    $response = beamResetResponse();
    $exited = false;

    try {
        $plugin->beamService->{$format}($model);
    } catch (ExitException) {
        $exited = true;
    }

    // Simulate the next request after Application::end() set the state to STATE_END.
    Craft::$app->state = Application::STATE_BEGIN;

    expect($exited)->toBeTrue();
    expect($response->getStatusCode())->toBe(302);

    $location = $response->getHeaders()->get('location');
    expect($location)->toBeString();

    parse_str((string)parse_url($location, PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('hash');

    $hash = $query['hash'];
    $config = $plugin->beamService->downloadHash($hash);
    expect($config)->toBeArray();
    expect($config['tempFilename'])->not->toBe($config['filename']);
    expect($config['tempFilename'])->toStartWith(BeamService::TEMP_DIR . '/');

    $fs = $plugin->beamService->getTempFs();
    expect($fs->fileExists($config['tempFilename']))->toBeTrue();

    $bytes = $fs->read($config['tempFilename']);
    expect($bytes)->toBeString();

    return compact('response', 'location', 'hash', 'config', 'bytes');
}

/**
 * @return array<string, string>
 */
function beamXlsxEntriesFromBytes(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'beam-xlsx-');
    expect($path)->toBeString();
    file_put_contents($path, $bytes);

    $zip = new ZipArchive();
    expect($zip->open($path))->toBeTrue();

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if ($name === false) {
            continue;
        }

        $content = $zip->getFromIndex($index);
        if ($content !== false) {
            $entries[$name] = $content;
        }
    }

    $zip->close();
    unlink($path);

    return $entries;
}

/**
 * @return list<list<string>>
 */
function beamXlsxRows(string $worksheetXml): array
{
    $document = new DOMDocument();
    expect($document->loadXML($worksheetXml))->toBeTrue();
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach ($xpath->query('//x:sheetData/x:row') ?: [] as $row) {
        $values = [];
        foreach ($xpath->query('./x:c', $row) ?: [] as $cell) {
            $inlineString = $xpath->query('./x:is/x:t', $cell)?->item(0);
            $value = $xpath->query('./x:v', $cell)?->item(0);
            $values[] = $inlineString?->textContent ?? $value?->textContent ?? '';
        }
        $rows[] = $values;
    }

    return $rows;
}

/**
 * @return list<string>
 */
function beamXlsxSheetNames(string $workbookXml): array
{
    $xml = simplexml_load_string($workbookXml);
    expect($xml)->not->toBeFalse();
    $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    return array_map(
        fn(SimpleXMLElement $sheet): string => (string)$sheet['name'],
        $xml->xpath('//x:sheets/x:sheet') ?: [],
    );
}

function beamResponseBytes(TestableResponse $response): string
{
    if (is_array($response->stream)) {
        [$handle, $begin, $end] = $response->stream;
        expect(is_resource($handle))->toBeTrue();
        fseek($handle, $begin);
        $bytes = stream_get_contents($handle, $end - $begin + 1);
        fclose($handle);
        expect($bytes)->toBeString();

        return $bytes;
    }

    if (is_resource($response->stream)) {
        rewind($response->stream);
        $bytes = stream_get_contents($response->stream);
        expect($bytes)->toBeString();

        return $bytes;
    }

    expect($response->content)->toBeString();

    return $response->content;
}

/**
 * @return array{location: string, bytes: string, tempFilename: string}
 */
function beamDownloadFixture(string $filename, string $mimeType, string $bytes): array
{
    $plugin = beamPlugin();
    $tempFilename = BeamService::TEMP_DIR . '/fixture-' . $filename;
    $plugin->beamService->ensureTempDirectory();
    $plugin->beamService->getTempFs()->write($tempFilename, $bytes);

    $config = [
        'filename' => $filename,
        'tempFilename' => $tempFilename,
        'mimeType' => $mimeType,
    ];
    $hash = Craft::$app->getSecurity()->hashData($plugin->beamService->hashConfig($config));
    $location = \craft\helpers\UrlHelper::siteUrl('beam/download', ['hash' => $hash]);

    return compact('location', 'bytes', 'tempFilename');
}

function beamFireAfterRequest(): void
{
    if (Craft::$app->hasEventHandlers(Application::EVENT_AFTER_REQUEST)) {
        Craft::$app->trigger(Application::EVENT_AFTER_REQUEST);
    }
}

beforeEach(function () {
    beamPlugin();
    beamResetSettings(false);
    beamPlugin()->beamService->clearTempFiles();
    beamResetResponse();
});

it('writes a parseable CSV with a BOM, formatted headers, commas, and newlines', function () {
    $model = new BeamModel([
        'filename' => 'people',
        'header' => [
            ['text' => 'Name', 'type' => 'string'],
            ['text' => 'Notes', 'type' => 'string'],
        ],
        'content' => [
            ['Doe, Jane', "Line one\nLine two"],
            ['Renée', 'Plain'],
        ],
    ]);

    $export = beamExport('csv', $model);

    expect($export['bytes'])->toStartWith(Bom::Utf8->value);
    expect($export['bytes'])->toContain('"Doe, Jane"');
    expect($export['bytes'])->toContain("\"Line one\nLine two\"");

    $reader = Reader::fromString($export['bytes']);
    expect(iterator_to_array($reader->getRecords(), false))->toBe([
        ['Name', 'Notes'],
        ['Doe, Jane', "Line one\nLine two"],
        ['Renée', 'Plain'],
    ]);
});

it('writes a single-sheet XLSX with exact header and cell values', function () {
    $model = new BeamModel([
        'filename' => 'single',
        'sheetName' => 'Report',
        'header' => [
            ['text' => 'Name', 'type' => 'string'],
            ['text' => 'Notes', 'type' => 'string'],
        ],
        'content' => [
            ['Jane', 'Ready'],
            ['John', 'Waiting'],
        ],
    ]);

    $export = beamExport('xlsx', $model);
    $entries = beamXlsxEntriesFromBytes($export['bytes']);

    expect($entries)->toHaveKeys([
        '[Content_Types].xml',
        'xl/workbook.xml',
        'xl/worksheets/sheet1.xml',
    ]);
    expect(beamXlsxSheetNames($entries['xl/workbook.xml']))->toBe(['Report']);
    expect(beamXlsxRows($entries['xl/worksheets/sheet1.xml']))->toBe([
        ['Name', 'Notes'],
        ['Jane', 'Ready'],
        ['John', 'Waiting'],
    ]);
});

it('writes two XLSX sheets with their own rows', function () {
    $model = new BeamModel([
        'filename' => 'multi',
        'sheets' => [
            [
                'name' => 'North',
                'header' => ['Region', 'Total'],
                'content' => [['Oslo', '10']],
            ],
            [
                'name' => 'South',
                'header' => ['Region', 'Total'],
                'content' => [['Rome', '20']],
            ],
        ],
    ]);

    $export = beamExport('xlsx', $model);
    $entries = beamXlsxEntriesFromBytes($export['bytes']);

    expect(beamXlsxSheetNames($entries['xl/workbook.xml']))->toBe(['North', 'South']);
    expect(beamXlsxRows($entries['xl/worksheets/sheet1.xml']))->toBe([
        ['Region', 'Total'],
        ['Oslo', '10'],
    ]);
    expect(beamXlsxRows($entries['xl/worksheets/sheet2.xml']))->toBe([
        ['Region', 'Total'],
        ['Rome', '20'],
    ]);
});

it('reflects sanitized and truncated sheet names in the workbook', function () {
    $sheetName = 'Bad\\/?*[]: Name That Is Much Too Long For Excel';
    $expected = trim(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $sheetName), 0, 31));
    $model = new BeamModel([
        'filename' => 'sheet-name',
        'sheets' => [[
            'name' => $sheetName,
            'content' => [['value']],
        ]],
    ]);

    $export = beamExport('xlsx', $model);
    $entries = beamXlsxEntriesFromBytes($export['bytes']);

    expect(beamXlsxSheetNames($entries['xl/workbook.xml']))->toBe([$expected]);
});

it('writes soft newlines and wrap-text styles into XLSX XML', function () {
    $model = new BeamModel([
        'filename' => 'wrapped',
        'header' => ['Notes'],
        'content' => [["Line one\nLine two"]],
        'wrapText' => true,
    ]);

    $export = beamExport('xlsx', $model);
    $entries = beamXlsxEntriesFromBytes($export['bytes']);

    expect(beamXlsxRows($entries['xl/worksheets/sheet1.xml']))->toBe([
        ['Notes'],
        ["Line one\nLine two"],
    ]);
    expect($entries['xl/styles.xml'])->toContain('wrapText="true"');
});

it('does not write or redirect for empty exports', function (string $format) {
    $response = beamResetResponse();
    $fs = Beam::$plugin->beamService->getTempFs();

    Beam::$plugin->beamService->{$format}(new BeamModel());

    expect($response->getHeaders()->has('location'))->toBeFalse();

    $files = [];
    if ($fs->directoryExists(BeamService::TEMP_DIR)) {
        foreach ($fs->getFileList(BeamService::TEMP_DIR, true) as $listing) {
            if (!$listing->getIsDir()) {
                $files[] = $listing->getUri();
            }
        }
    }

    expect($files)->toBe([]);
})->with(['csv', 'xlsx']);

it('redirects CSV exports to a signed site download URL on the temp asset upload FS', function () {
    $export = beamExport('csv', new BeamModel([
        'filename' => 'display name',
        'content' => [['value']],
    ]));

    $location = parse_url($export['location']);
    expect($location['path'])->toBe('/beam/download');
    expect($export['config']['filename'])->toBe('display name.csv');
    expect($export['config']['mimeType'])->toBe('text/csv');
    expect($export['config']['tempFilename'])->toMatch('#^beam/[A-Za-z0-9]+-display name\.csv$#');
    expect(Craft::$app->getSecurity()->validateData($export['hash']))->not->toBeFalse();
});

it('redirects XLSX exports with a valid download config', function () {
    $export = beamExport('xlsx', new BeamModel([
        'filename' => 'report',
        'content' => [['value']],
    ]));

    expect(parse_url($export['location'], PHP_URL_PATH))->toBe('/beam/download');
    expect($export['config']['filename'])->toBe('report.xlsx');
    expect($export['config']['mimeType'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($export['config']['tempFilename'])->toStartWith('beam/');
});

it('downloads the generated file anonymously with display filename, MIME, and exact body', function (string $format, string $mime) {
    $fixture = beamDownloadFixture(
        "customer report.$format",
        $mime,
        "exact $format download bytes",
    );

    $download = $this->get($fixture['location']);

    $download->assertOk();
    $download->assertDownload("customer report.$format");
    $download->assertHeader('content-type', $mime);
    expect(beamResponseBytes($download))->toBe($fixture['bytes']);

    $controller = new DefaultController('default', Beam::$plugin);
    $property = new ReflectionProperty($controller, 'allowAnonymous');
    expect($property->getValue($controller))->toHaveKey('index');
})->with([
    'csv' => ['csv', 'text/csv'],
    'xlsx' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('keeps the temp file after download when delete-after-download is disabled', function () {
    beamResetSettings(false);
    $fixture = beamDownloadFixture('keep.csv', 'text/csv', 'keep-me');

    $download = $this->get($fixture['location']);
    $download->assertOk();
    beamFireAfterRequest();

    expect(Beam::$plugin->beamService->getTempFs()->fileExists($fixture['tempFilename']))->toBeTrue();
});

it('deletes the temp file after download when delete-after-download is enabled', function () {
    beamResetSettings(true);
    $fixture = beamDownloadFixture('remove.csv', 'text/csv', 'remove-me');

    $download = $this->get($fixture['location']);
    $download->assertOk();
    beamFireAfterRequest();

    expect(Beam::$plugin->beamService->getTempFs()->fileExists($fixture['tempFilename']))->toBeFalse();
});

it('registers a Clear Caches option that removes beam/ temp objects', function () {
    $plugin = beamPlugin();
    $fs = $plugin->beamService->getTempFs();
    $plugin->beamService->ensureTempDirectory();
    $dummyPath = BeamService::TEMP_DIR . '/clear-me.csv';
    $fs->write($dummyPath, 'dummy');

    $options = ClearCaches::cacheOptions();
    $beamOption = null;
    foreach ($options as $option) {
        if (($option['key'] ?? null) === 'beam-temp') {
            $beamOption = $option;
            break;
        }
    }

    expect($beamOption)->not->toBeNull();
    expect($beamOption['label'])->toBe('Beam temp files');
    expect($beamOption['info'])->toBe('CSV and XLSX files written during exports');
    expect($beamOption['action'])->toBeCallable();

    ($beamOption['action'])();

    expect($fs->fileExists($dummyPath))->toBeFalse();
});

it('no-ops clearTempFiles when the beam directory is missing', function () {
    $plugin = beamPlugin();
    $fs = $plugin->beamService->getTempFs();

    if ($fs->directoryExists(BeamService::TEMP_DIR)) {
        foreach ($fs->getFileList(BeamService::TEMP_DIR, true) as $listing) {
            if (!$listing->getIsDir()) {
                $fs->deleteFile($listing->getUri());
            }
        }
        $fs->deleteDirectory(BeamService::TEMP_DIR);
    }

    expect(fn() => $plugin->beamService->clearTempFiles())->not->toThrow(Throwable::class);
});

it('rejects a tampered or unsigned download hash', function (string $hash) {
    expect(fn() => $this->get('/beam/download?hash=' . urlencode($hash)))
        ->toThrow(NotFoundHttpException::class);
})->with([
    'tampered' => fn() => Craft::$app->getSecurity()->hashData('payload') . 'tampered',
    'unsigned' => fn() => base64_encode('report.csv||beam/random-report.csv||text/csv'),
]);

it('rejects a download request without a hash', function () {
    expect(fn() => $this->get('/beam/download'))
        ->toThrow(BadRequestHttpException::class);
});

it('parses env-aware delete-after-download settings', function () {
    $settings = new Settings();
    $settings->deleteFilesAfterDownload = false;
    expect($settings->getDeleteFilesAfterDownload())->toBeFalse();

    $settings->deleteFilesAfterDownload = true;
    expect($settings->getDeleteFilesAfterDownload())->toBeTrue();

    putenv('BEAM_DELETE_FILES_TEST=true');
    $_ENV['BEAM_DELETE_FILES_TEST'] = 'true';
    $settings->deleteFilesAfterDownload = '$BEAM_DELETE_FILES_TEST';
    expect(App::parseBooleanEnv($settings->deleteFilesAfterDownload))->toBeTrue();
    expect($settings->getDeleteFilesAfterDownload())->toBeTrue();
});
