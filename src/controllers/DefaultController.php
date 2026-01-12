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

        // Stream the file directly to avoid memory exhaustion with large files
        try {
            $stream = $fs->readStream($tempFilename);
            return Craft::$app->getResponse()->sendStreamAsFile($stream, $filename, [
                'mimeType' => $config['mimeType'],
            ]);
        } catch (\Throwable $e) {
            // Fallback to reading content if streaming is not supported
            Craft::warning("Failed to stream file, falling back to content read: {$e->getMessage()}", __METHOD__);
            $content = $fs->read($tempFilename);
            return Craft::$app->getResponse()->sendContentAsFile($content, $filename, [
                'mimeType' => $config['mimeType'],
            ]);
        }
    }
}
