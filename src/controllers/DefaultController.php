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
use Throwable;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 */
class DefaultController extends Controller
{
    protected array|int|bool $allowAnonymous = ['index'];

    /**
     * @return Response
     * @throws NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $hash = $request->getRequiredParam('hash');

        $config = Beam::$plugin->beamService->downloadHash($hash);

        if (!$config) {
            throw new NotFoundHttpException();
        }

        $tempFilename = $config['tempFilename'];
        $filename = $config['filename'];
        $fs = Beam::$plugin->beamService->getTempFs();

        if (!$fs->fileExists($tempFilename)) {
            throw new NotFoundHttpException();
        }

        if (Beam::$plugin->getSettings()->getDeleteFilesAfterDownload()) {
            Craft::$app->onAfterRequest(function() use ($fs, $tempFilename) {
                try {
                    $fs->deleteFile($tempFilename);
                } catch (Throwable $e) {
                    Craft::warning(
                        "Failed to delete temporary Beam file {$tempFilename}: {$e->getMessage()}",
                        __METHOD__
                    );
                }
            });
        }

        try {
            $stream = $fs->getFileStream($tempFilename);
            $options = [
                'mimeType' => $config['mimeType'],
            ];

            try {
                $options['fileSize'] = $fs->getFileSize($tempFilename);
            } catch (Throwable) {
                // Optional; some filesystems may not report size.
            }

            return Craft::$app->getResponse()->sendStreamAsFile($stream, $filename, $options);
        } catch (Throwable $e) {
            Craft::warning(
                "Failed to stream Beam file {$tempFilename}, falling back to content read: {$e->getMessage()}",
                __METHOD__
            );

            return Craft::$app->getResponse()->sendContentAsFile($fs->read($tempFilename), $filename, [
                'mimeType' => $config['mimeType'],
            ]);
        }
    }
}
