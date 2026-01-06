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

        $filename = $config['filename'];
        $mimeType = $config['mimeType'];

        // Handle filesystem storage
        if (!empty($config['useFilesystem'])) {
            $content = Beam::$plugin->beamService->readFromFilesystem($config['tempFilename']);
            
            // Optionally clean up the temp file after download
            // Beam::$plugin->beamService->deleteFromFilesystem($config['tempFilename']);
            
            return Craft::$app->getResponse()->sendContentAsFile($content, $filename, [
                'mimeType' => $mimeType,
            ]);
        }

        // Handle local file storage
        $path = $config['path'];
        return Craft::$app->getResponse()->sendFile($path, $filename, [
            'mimeType' => $mimeType,
        ]);
    }
}
