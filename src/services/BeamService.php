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
use craft\base\Fs;
use craft\base\FsInterface;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;

use craft\helpers\UrlHelper;
use League\Csv\Writer;
use superbig\beam\Beam;
use superbig\beam\models\BeamModel;
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
    public function create($config = [])
    {
        $model = new BeamModel($config);

        return $model;
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

        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Writer::BOM_UTF8);

        if (!empty($header)) {
            $headerValues = array_map(fn($value) => is_array($value) ? $value['text'] ?? 'No text set' : $value, $header);
            $csv->insertOne($header);
        }

        $mimeType = 'text/csv';

        // Insert all the rows
        $csv->insertAll($content);

        // @todo Remove this once all plugins is using 9.0
        $content = method_exists($csv, 'getContent') ? $csv->getContent() : (string)$csv;

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
        $header = $model->header;
        $content = $model->content;

        if (empty($header) && empty($content)) {
            return;
        }

        // Create temp directory if using local storage
        if (!$this->useFilesystemStorage()) {
            $tempPath = $this->getTempPath();
            if (!file_exists($tempPath) && !is_dir($tempPath)) {
                FileHelper::createDirectory($tempPath);
            }
        }

        // Load the CSV document from a string
        $writer = new XLSXWriter();
        $sheetName = !empty($model->sheetName) ? $model->sheetName : 'Sheet';

        if (!empty($header)) {
            $headers = [];
            foreach ($header as $header) {
                if (is_array($header)) {
                    $text = $header['text'] ?? 'No text set';
                    $type = $this->normalizeCellFormat($header['type'] ?? 'string');
                    $headers[ $text ] = $type;
                } else {
                    $headers[ $header ] = 'string';
                }
            }
            // Insert the headers
            $writer->writeSheetHeader($sheetName, $headers);
        }

        foreach ($content as $row) {
            $writer->writeSheetRow($sheetName, $row);
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

        $config = $this->unhashConfig($hash);

        // Determine the path based on storage type
        if ($this->useFilesystemStorage()) {
            $config['path'] = null; // Will be read from filesystem
            $config['useFilesystem'] = true;
        } else {
            $config['path'] = $this->getTempPath() . $config['tempFilename'];
            $config['useFilesystem'] = false;
        }

        return $config;
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
        $tempFilename = StringHelper::randomString(12) . "-{$filename}";
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

        // Write the file based on storage type
        if ($this->useFilesystemStorage()) {
            $this->writeToFilesystem($tempFilename, $content);
        } else {
            $tempPath = $this->getTempPath();
            FileHelper::writeToFile($tempPath . $tempFilename, $content);
        }

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

    /**
     * Check if filesystem storage is configured
     */
    private function useFilesystemStorage(): bool
    {
        $settings = Beam::$plugin->getSettings();
        return !empty($settings->tempFilesystemHandle);
    }

    /**
     * Get the configured filesystem
     * @throws InvalidConfigException
     */
    private function getFilesystem(): ?FsInterface
    {
        $settings = Beam::$plugin->getSettings();
        
        if (empty($settings->tempFilesystemHandle)) {
            return null;
        }

        $volume = Craft::$app->getVolumes()->getVolumeByHandle($settings->tempFilesystemHandle);
        
        if (!$volume) {
            throw new InvalidConfigException("Filesystem volume with handle '{$settings->tempFilesystemHandle}' not found.");
        }

        return $volume->getFs();
    }

    /**
     * Get the temp path for local storage
     */
    private function getTempPath(): string
    {
        return Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'beam' . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the subfolder path within the filesystem
     */
    private function getFilesystemSubfolder(): string
    {
        $settings = Beam::$plugin->getSettings();
        return rtrim($settings->tempSubfolder ?? 'beam', '/') . '/';
    }

    /**
     * Write content to the configured filesystem
     * @throws InvalidConfigException
     * @throws Exception
     */
    private function writeToFilesystem(string $filename, string $content): void
    {
        $fs = $this->getFilesystem();
        $path = $this->getFilesystemSubfolder() . $filename;
        
        $stream = fopen('php://temp', 'r+');
        
        if ($stream === false) {
            throw new Exception("Failed to create temporary stream for writing file: {$filename}");
        }
        
        fwrite($stream, $content);
        rewind($stream);
        
        $fs->writeFileFromStream($path, $stream, []);
        
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * Read content from the configured filesystem
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function readFromFilesystem(string $filename): string
    {
        $fs = $this->getFilesystem();
        $path = $this->getFilesystemSubfolder() . $filename;
        
        $stream = $fs->getFileStream($path);
        $content = stream_get_contents($stream);
        
        if (is_resource($stream)) {
            fclose($stream);
        }
        
        if ($content === false) {
            throw new Exception("Failed to read content from file: {$filename}");
        }
        
        return $content;
    }

    /**
     * Delete file from the configured filesystem
     * @throws InvalidConfigException
     */
    public function deleteFromFilesystem(string $filename): void
    {
        $fs = $this->getFilesystem();
        $path = $this->getFilesystemSubfolder() . $filename;
        
        if ($fs->fileExists($path)) {
            $fs->deleteFile($path);
        }
    }
}
