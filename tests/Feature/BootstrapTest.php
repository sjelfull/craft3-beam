<?php

use craft\web\Application;
use superbig\beam\Beam;

it('boots Craft', function () {
    expect(Craft::$app)->toBeInstanceOf(Application::class);
});

it('installs and boots the Beam plugin', function () {
    $plugins = Craft::$app->getPlugins();

    if (!$plugins->isPluginInstalled('beam')) {
        $plugins->installPlugin('beam');
    } elseif (!$plugins->isPluginEnabled('beam')) {
        $plugins->enablePlugin('beam');
    }

    expect($plugins->isPluginEnabled('beam'))->toBeTrue();
    expect(Beam::$plugin)->toBeInstanceOf(Beam::class);
    expect(Beam::$plugin->beamService)->toBeInstanceOf(\superbig\beam\services\BeamService::class);
});
