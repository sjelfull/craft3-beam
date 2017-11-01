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
use superbig\beam\Beam;

use Craft;
use craft\base\Component;
use League\Csv\Writer;
use League\Csv\Reader;
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

    /**
     * @param array $options
     *
     * @return null
     */
    public function csv ($options = [])
    {
        if ( empty($options['headers']) && empty($options['rows']) ) {
            return null;
        }
        // Load the CSV document from a string
        $csv      = Writer::createFromString('');
        $filename = !empty($options['filename']) ? $options['filename'] : 'output.csv';
        if ( !empty($options['header']) ) {
            // Insert the headers
            $csv->insertOne($options['header']);
        }
        // Insert all the rows
        $csv->insertAll($options['rows']);

        $csv->output($filename);
    }

    /**
     * @param array $options
     *
     * @return null
     */
    public function xlsx ($options = [])
    {
        $tempPath = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . 'beam' . DIRECTORY_SEPARATOR;

        if ( empty($options['headers']) && empty($options['rows']) ) {
            return null;
        }

        if ( !file_exists($tempPath) && !is_dir($tempPath) ) {
            FileHelper::createDirectory($tempPath);
        }

        // Load the CSV document from a string
        $writer    = new XLSXWriter();
        $filename  = !empty($options['filename']) ? $options['filename'] : 'output.xlsx';
        $sheetName = isset($options['sheetName']) ? $options['sheetName'] : 'Sheet';

        if ( !empty($options['header']) ) {
            $headers = [];
            foreach ($options['header'] as $header) {
                $headers[ $header ] = 'string';
            }
            // Insert the headers
            $writer->writeSheetHeader($sheetName, $headers);
        }

        $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);

        foreach ($options['rows'] as $row) {
            $writer->writeSheetRow($sheetName, $row);
        }

        $writer->writeToFile($tempPath . $filename);

        //$mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        Craft::$app->response->sendFile($tempPath . $filename, $filename);
    }
}
