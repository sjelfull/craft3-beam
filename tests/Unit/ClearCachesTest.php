<?php

use craft\utilities\ClearCaches;
use superbig\beam\Beam;
use superbig\beam\services\BeamService;

it('registers a beam-temp clear-caches option that empties the Beam temp directory', function () {
    $plugins = Craft::$app->getPlugins();

    if (!$plugins->isPluginInstalled('beam')) {
        $plugins->installPlugin('beam');
    } elseif (!$plugins->isPluginEnabled('beam')) {
        $plugins->enablePlugin('beam');
    }

    expect(Beam::$plugin)->toBeInstanceOf(Beam::class);
    expect(Beam::$plugin->beamService)->toBeInstanceOf(BeamService::class);

    $options = ClearCaches::cacheOptions();
    $beamOption = collect($options)->firstWhere('key', 'beam-temp');

    expect($beamOption)->not->toBeNull();
    expect($beamOption['action'])->toBeCallable();

    $dir = Beam::$plugin->beamService->getTempPath();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $dummyFile = $dir . DIRECTORY_SEPARATOR . 'dummy-export.csv';
    file_put_contents($dummyFile, 'name,value' . PHP_EOL . 'a,1');

    expect(file_exists($dummyFile))->toBeTrue();

    ($beamOption['action'])();

    expect(file_exists($dummyFile))->toBeFalse();
});
