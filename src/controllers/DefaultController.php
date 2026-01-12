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
use craft\web\Response;
use superbig\beam\Beam;
use yii\base\Event;
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
            // Use Yii's native event to clean up the file after the response is sent
            Event::on(
                Response::class,
                Response::EVENT_AFTER_SEND,
                function() use ($path) {
                    if (file_exists($path)) {
                        if (!unlink($path)) {
                            Craft::warning("Failed to delete temporary file: {$path}", __METHOD__);
                        }
                    }
                }
            );
        }

        return Craft::$app->getResponse()->sendFile($path, $filename, [
            'mimeType' => $config['mimeType'],
        ]);
    }
}
