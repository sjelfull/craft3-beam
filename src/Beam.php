<?php
/**
 * Beam plugin for Craft CMS 3.x
 *
 * Generate CSVs and XLS files in your templates
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\beam;

use Craft;
use craft\base\Model;
use craft\base\Plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use superbig\beam\models\Settings;
use superbig\beam\services\BeamService as BeamServiceService;
use superbig\beam\variables\BeamVariable;

use yii\base\Event;

/**
 * Class Beam
 *
 * @author    Superbig
 * @package   Beam
 * @since     2.0.0
 *
 * @property  BeamServiceService $beamService
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Beam extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Beam
     */
    public static $plugin;

    /**
     * @var string
     */
    public string $schemaVersion = '5.1.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['beam/download'] = 'beam/default';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['beam/download'] = 'beam/default';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('beam', BeamVariable::class);
            }
        );

        Craft::info(
            Craft::t(
                'beam',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate(
            'beam/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
