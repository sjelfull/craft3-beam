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
use craft\base\Plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
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
 */
class Beam extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Beam
     */
    public static $plugin;

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
}
