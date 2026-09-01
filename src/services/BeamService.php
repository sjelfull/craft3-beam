<?php
/**
 * Beam plugin for Craft CMS 3.x
 *
 * Generate CSVs and XLS files in your templates
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\beam\services;

use Craft;
use craft\base\Component;
use craft\base\FsInterface;
use craft\helpers\StringHelper;

use craft\helpers\UrlHelper;
use League\Csv\Bom;
use League\Csv\Writer;
use superbig\beam\models\BeamModel;
use Throwable;
use XLSXWriter;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class BeamService extends Component
{
    /**
     * Directory prefix for Beam objects on the temp asset upload filesystem.
     */
    public const TEMP_DIR = 'beam';

    public function create($config = [])
    {
        $model = new BeamModel($config);

        return $model;
    }

    /**
     * Returns Craft's temp asset upload filesystem (shared across instances when configured).
     *
     * @throws InvalidConfigException
     */
    public function getTempFs(): FsInterface
    {
        return Craft::$app->getAssets()->getTempAssetUploadFs();
    }

    /**
     * Builds a Beam-prefixed object path on the temp asset upload filesystem.
     */
    public function buildTempObjectPath(string $filename): string
    {
        return self::TEMP_DIR . '/' . StringHelper::randomString(12) . "-{$filename}";
    }

    /**
     * Ensures the Beam temp directory exists on the filesystem.
     *
     * @throws InvalidConfigException
     */
    public function ensureTempDirectory(): void
    {
        $fs = $this->getTempFs();

        if (!$fs->directoryExists(self::TEMP_DIR)) {
            $fs->createDirectory(self::TEMP_DIR);
        }
    }

    /**
     * Writes export contents to the temp asset upload filesystem.
     *
     * @return string The object path (including the beam/ prefix)
     * @throws InvalidConfigException
     */
    public function writeTempFile(string $content, string $filename): string
    {
        $this->ensureTempDirectory();

        $path = $this->buildTempObjectPath($filename);
        $this->getTempFs()->write($path, $content);

        return $path;
    }

    /**
     * Deletes all Beam temp objects under the beam/ prefix.
     *
     * No-ops when the beam/ directory does not exist. Works for local and remote filesystems.
     *
     * @throws InvalidConfigException
     */
    public function clearTempFiles(): void
    {
        $fs = $this->getTempFs();

        if (!$fs->directoryExists(self::TEMP_DIR)) {
            return;
        }

        foreach ($fs->getFileList(self::TEMP_DIR, true) as $listing) {
            if ($listing->getIsDir()) {
                continue;
            }

            try {
                $fs->deleteFile($listing->getUri());
            } catch (Throwable $e) {
                Craft::warning(
                    "Failed to delete Beam temp file {$listing->getUri()}: {$e->getMessage()}",
                    __METHOD__
                );
            }
        }
    }

    /**
     * @param BeamModel $model
     *
     * @return void
     * @throws \League\Csv\CannotInsertRecord
     */
    public function csv(BeamModel $model): void
    {
        $header = $model->header;
        $content = $model->content;

        if (empty($header) && empty($content)) {
            return;
        }

        $csv = method_exists(Writer::class, 'fromString')
            ? Writer::fromString('')
            : Writer::createFromString('');
        $csv->setOutputBOM(
            enum_exists(Bom::class) ? Bom::Utf8->value : Writer::BOM_UTF8
        );

        if (!empty($header)) {
            $headerValues = array_map(fn($value) => is_array($value) ? $value['text'] ?? 'No text set' : $value, $header);
            $csv->insertOne($headerValues);
        }

        $mimeType = 'text/csv';

        // Insert all the rows
        $csv->insertAll($content);

        $content = method_exists($csv, 'toString')
            ? $csv->toString()
            : (method_exists($csv, 'getContent') ? $csv->getContent() : (string)$csv);

        $this->writeAndRedirect($content, $model->getFilename('csv'), $mimeType);
    }

    /**
     * @throws ErrorException
     * @throws Exception
     * @throws ExitException
     * @throws InvalidConfigException
     * @throws InvalidRouteException
     */
    public function xlsx(BeamModel $model): void
    {
        $writer = new XLSXWriter();
        $sheetsWritten = 0;

        // Check if multiple sheets are configured
        if (count($model->sheets) > 0) {
            // Handle multiple sheets
            foreach ($model->sheets as $index => $sheet) {
                $sheetName = $sheet['name'] ?? 'Sheet' . ($index + 1);
                $sheetName = $this->sanitizeSheetName($sheetName);
                $sheetHeader = $sheet['header'] ?? [];
                $sheetContent = $sheet['content'] ?? [];

                if (empty($sheetHeader) && empty($sheetContent)) {
                    continue;
                }

                $this->writeSheet($writer, $sheetName, $sheetHeader, $sheetContent, $model);
                $sheetsWritten++;
            }

            // If no sheets were written, return early to avoid creating invalid Excel file
            if ($sheetsWritten === 0) {
                return;
            }
        } else {
            // Handle single sheet (backward compatibility)
            $header = $model->header;
            $content = $model->content;

            if (empty($header) && empty($content)) {
                return;
            }

            $sheetName = !empty($model->sheetName) ? $model->sheetName : 'Sheet';
            $this->writeSheet($writer, $sheetName, $header, $content, $model);
        }

        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $this->writeAndRedirect($writer->writeToString(), $model->getFilename('xlsx'), $mimeType);
    }

    /**
     * @param $fileHash
     * @return array|bool
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function downloadHash($fileHash = null): array | bool
    {
        $hash = Craft::$app->getSecurity()->validateData($fileHash);

        if (!$hash) {
            return false;
        }

        return $this->unhashConfig($hash);
    }

    /**
     * @throws InvalidRouteException
     * @throws InvalidConfigException
     * @throws ErrorException
     * @throws Exception
     * @throws ExitException
     */
    private function writeAndRedirect(string $content, string $filename, string $mimeType): void
    {
        $tempFilename = $this->writeTempFile($content, $filename);
        $config = [
            'filename' => $filename,
            'tempFilename' => $tempFilename,
            'mimeType' => $mimeType,
        ];

        $hashConfig = $this->hashConfig($config);
        $verifyHash = Craft::$app->getSecurity()->hashData($hashConfig);
        $url = UrlHelper::siteUrl('beam/download', [
            'hash' => $verifyHash,
        ]);

        Craft::$app->getResponse()->redirect($url);

        Craft::$app->end();
    }

    public function hashConfig($config = []): string
    {
        $string = implode('||', $config);

        return base64_encode($string);
    }

    public function unhashConfig(string $hash): array
    {
        $config = base64_decode($hash);
        $config = explode('||', $config);

        list($filename, $tempFilename, $mimeType) = $config;

        $config = [
            'filename' => $filename,
            'tempFilename' => $tempFilename,
            'mimeType' => $mimeType,
        ];

        return $config;
    }

    private function writeSheet(XLSXWriter $writer, string $sheetName, array $header, array $content, BeamModel $model): void
    {
        if (!empty($header)) {
            $headers = [];
            foreach ($header as $headerItem) {
                if (is_array($headerItem)) {
                    $text = $headerItem['text'] ?? 'No text set';
                    $type = $this->normalizeCellFormat($headerItem['type'] ?? 'string');
                    $headers[ $text ] = $type;
                } else {
                    $headers[ $headerItem ] = 'string';
                }
            }
            // Insert the headers
            $writer->writeSheetHeader($sheetName, $headers);
        }

        foreach ($content as $row) {
            $rowStyle = $model->wrapText ? ['wrap_text' => true] : [];
            $writer->writeSheetRow($sheetName, $row, $rowStyle);
        }
    }

    private function sanitizeSheetName(string $name): string
    {
        // Excel sheet names have these constraints:
        // - Maximum 31 characters
        // - Cannot contain: \ / ? * [ ] :
        // - Cannot be empty

        // Remove invalid characters
        $name = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $name);

        // Trim whitespace
        $name = trim($name);

        // Limit to 31 characters
        if (mb_strlen($name) > 31) {
            $name = mb_substr($name, 0, 31);
            // Trim again in case truncation left trailing spaces
            $name = trim($name);
        }

        // Ensure not empty
        if (empty($name)) {
            $name = 'Sheet';
        }

        return $name;
    }

    private function normalizeCellFormat(string $type): string
    {
        $types = [
            'number' => 'integer',
            'date' => 'date',
            'datetime' => 'datetime',
            'time' => 'time',
            'dollar' => 'dollar',
            'euro' => 'euro',
            'price' => 'price',
            'string' => 'string',
        ];

        return $types[$type] ?? 'string';
    }
}
