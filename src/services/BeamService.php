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
        $fs = Craft::$app->getTempAssetUploadFs();
        $tempFilename = 'beam/' . StringHelper::randomString(12) . "-{$filename}";
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

        $fs->write($tempFilename, $content);

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
}
