<?php

use craft\helpers\FileHelper;
use craft\web\Application;
use League\Csv\Bom;
use League\Csv\Reader;
use markhuot\craftpest\web\TestableResponse;
use superbig\beam\Beam;
use superbig\beam\controllers\DefaultController;
use superbig\beam\models\BeamModel;
use superbig\beam\services\BeamService;
use yii\base\ExitException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

function beamTempPath(): string
{
    return Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . 'beam';
}

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

/**
 * @return array{
 *   response: TestableResponse,
 *   location: string,
 *   hash: string,
 *   config: array{filename: string, tempFilename: string, mimeType: string, path: string},
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
    expect(is_file($config['path']))->toBeTrue();

    $bytes = file_get_contents($config['path']);
    expect($bytes)->toBeString();

    return compact('response', 'location', 'hash', 'config', 'bytes');
}

/**
 * @return array<string, string>
 */
function beamXlsxEntries(string $path): array
{
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
    expect($response->stream)->toBeArray();

    [$handle, $begin, $end] = $response->stream;
    expect(is_resource($handle))->toBeTrue();
    fseek($handle, $begin);
    $bytes = stream_get_contents($handle, $end - $begin + 1);
    fclose($handle);

    expect($bytes)->toBeString();

    return $bytes;
}

/**
 * @return array{location: string, bytes: string}
 */
function beamDownloadFixture(string $filename, string $mimeType, string $bytes): array
{
    $plugin = beamPlugin();
    $tempFilename = 'fixture-' . $filename;
    FileHelper::writeToFile(beamTempPath() . DIRECTORY_SEPARATOR . $tempFilename, $bytes);

    $config = [
        'filename' => $filename,
        'tempFilename' => $tempFilename,
        'mimeType' => $mimeType,
    ];
    $hash = Craft::$app->getSecurity()->hashData($plugin->beamService->hashConfig($config));
    $location = \craft\helpers\UrlHelper::siteUrl('beam/download', ['hash' => $hash]);

    return compact('location', 'bytes');
}

beforeEach(function () {
    beamPlugin();

    $tempPath = beamTempPath();
    if (is_dir($tempPath)) {
        FileHelper::clearDirectory($tempPath);
    } else {
        FileHelper::createDirectory($tempPath);
    }

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
    $entries = beamXlsxEntries($export['config']['path']);

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
    $entries = beamXlsxEntries($export['config']['path']);

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
    $entries = beamXlsxEntries($export['config']['path']);

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
    $entries = beamXlsxEntries($export['config']['path']);

    expect(beamXlsxRows($entries['xl/worksheets/sheet1.xml']))->toBe([
        ['Notes'],
        ["Line one\nLine two"],
    ]);
    expect($entries['xl/styles.xml'])->toContain('wrapText="true"');
});

it('does not write or redirect for empty exports', function (string $format) {
    $response = beamResetResponse();

    Beam::$plugin->beamService->{$format}(new BeamModel());

    expect($response->getHeaders()->has('location'))->toBeFalse();
    expect(glob(beamTempPath() . DIRECTORY_SEPARATOR . '*') ?: [])->toBe([]);
})->with(['csv', 'xlsx']);

it('redirects CSV exports to a signed site download URL', function () {
    $export = beamExport('csv', new BeamModel([
        'filename' => 'display name',
        'content' => [['value']],
    ]));

    $location = parse_url($export['location']);
    expect($location['path'])->toBe('/beam/download');
    expect($export['config']['filename'])->toBe('display name.csv');
    expect($export['config']['mimeType'])->toBe('text/csv');
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

it('rejects a tampered or unsigned download hash', function (string $hash) {
    expect(fn() => $this->get('/beam/download?hash=' . urlencode($hash)))
        ->toThrow(NotFoundHttpException::class);
})->with([
    'tampered' => fn() => Craft::$app->getSecurity()->hashData('payload') . 'tampered',
    'unsigned' => fn() => base64_encode('report.csv||random-report.csv||text/csv'),
]);

it('rejects a download request without a hash', function () {
    expect(fn() => $this->get('/beam/download'))
        ->toThrow(BadRequestHttpException::class);
});
