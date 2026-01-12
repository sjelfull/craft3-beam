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

use craft\helpers\FileHelper;
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

        $path = $config['path'];
        $filename = $config['filename'];

        // Check if automatic cleanup is enabled
        if (Beam::$plugin->getSettings()->deleteFilesAfterDownload) {
            // Use Craft's onAfterRequest to clean up the file after the request is complete
            Craft::$app->onAfterRequest(function() use ($path) {
                try {
                    if (file_exists($path)) {
                        FileHelper::unlink($path);
                    }
                } catch (\Throwable $e) {
                    Craft::warning("Failed to delete temporary file: {$path}. Error: " . $e->getMessage(), __METHOD__);
                }
            });
        }

        return Craft::$app->getResponse()->sendFile($path, $filename, [
            'mimeType' => $config['mimeType'],
        ]);
    }
}
