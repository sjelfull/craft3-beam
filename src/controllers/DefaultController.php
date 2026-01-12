<?php
/**
 * Beam plugin for Craft CMS 3.x
 *
 * Generate CSVs and XLS files in your templates
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\beam\controllers;

use Craft;

use craft\web\Controller;
use superbig\beam\Beam;
use yii\web\NotFoundHttpException;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class DefaultController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index'];

    /**
     * @return mixed
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionIndex()
    {
        $request = Craft::$app->getRequest();
        $hash = $request->getRequiredParam('hash');

        $config = Beam::$plugin->beamService->downloadHash($hash);

        if (!$config) {
            throw new NotFoundHttpException();
        }

        $tempFilename = $config['tempFilename'];
        $filename = $config['filename'];
        $fs = Craft::$app->getTempAssetUploadFs();

        // Check if file exists in the filesystem
        if (!$fs->fileExists($tempFilename)) {
            throw new NotFoundHttpException('File not found');
        }

        // Read the file content from the filesystem
        $content = $fs->read($tempFilename);

        // Check if automatic cleanup is enabled
        if (Beam::$plugin->getSettings()->deleteFilesAfterDownload) {
            // Use Craft's onAfterRequest to clean up the file after the request is complete
            Craft::$app->onAfterRequest(function() use ($fs, $tempFilename) {
                try {
                    if ($fs->fileExists($tempFilename)) {
                        $fs->deleteFile($tempFilename);
                    }
                } catch (\Throwable $e) {
                    Craft::warning("Failed to delete temporary file: {$tempFilename}. Error: " . $e->getMessage(), __METHOD__);
                }
            });
        }

        // Send the file content as a download
        return Craft::$app->getResponse()->sendContentAsFile($content, $filename, [
            'mimeType' => $config['mimeType'],
        ]);
    }
}
