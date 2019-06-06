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

use craft\helpers\FileHelper;
use craft\helpers\Path;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use superbig\beam\Beam;

use Craft;
use craft\base\Component;
use League\Csv\Writer;
use League\Csv\Reader;
use superbig\beam\models\BeamModel;
use XLSXWriter;
use yii\web\Response;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class BeamService extends Component
{
    // Public Methods
    // =========================================================================

    public function create($config = [])
    {
        $model = new BeamModel($config);

        return $model;
    }

    /**
     * @param BeamModel $model
     *
     * @return null
     * @throws \League\Csv\CannotInsertRecord
     */
    public function csv(BeamModel $model)
    {
        $header  = $model->header;
        $content = $model->content;

        if (empty($header) && empty($content)) {
            return null;
        }

        $csv = Writer::createFromString('');

        if (!empty($header)) {
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
     * @param BeamModel $model
     *
     * @return null
     * @throws \yii\base\Exception
     */
    public function xlsx(BeamModel $model)
    {
        $tempPath = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'beam' . DIRECTORY_SEPARATOR;
        $header   = $model->header;
        $content  = $model->content;

        if (empty($header) && empty($content)) {
            return null;
        }

        if (!file_exists($tempPath) && !is_dir($tempPath)) {
            FileHelper::createDirectory($tempPath);
        }

        // Load the CSV document from a string
        $writer    = new XLSXWriter();
        $sheetName = !empty($model->sheetName) ? $model->sheetName : 'Sheet';

        if (!empty($header)) {
            $headers = [];
            foreach ($header as $header) {
                $headers[ $header ] = 'string';
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

    public function downloadHash($fileHash = null)
    {
        $hash = Craft::$app->getSecurity()->validateData($fileHash);

        if (!$hash) {
            return false;
        }
        $config = $this->unhashConfig($hash);

        $config['path'] = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'beam' . DIRECTORY_SEPARATOR . $config['tempFilename'];

        return $config;
    }

    private function writeAndRedirect($content, $filename, $mimeType)
    {
        $tempPath     = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'beam' . DIRECTORY_SEPARATOR;
        $tempFilename = StringHelper::randomString(12) . "-{$filename}";
        $config       = [
            'filename'     => $filename,
            'tempFilename' => $tempFilename,
            'mimeType'     => $mimeType,
        ];

        $hashConfig = $this->hashConfig($config);
        $verifyHash = Craft::$app->getSecurity()->hashData($hashConfig);
        $url        = UrlHelper::siteUrl('beam/download', [
            'hash' => $verifyHash,
        ]);

        FileHelper::writeToFile($tempPath . $tempFilename, $content);

        Craft::$app->getResponse()->redirect($url);

        return Craft::$app->end();
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

        list ($filename, $tempFilename, $mimeType) = $config;

        $config = [
            'filename'     => $filename,
            'tempFilename' => $tempFilename,
            'mimeType'     => $mimeType,
        ];

        return $config;
    }
}
