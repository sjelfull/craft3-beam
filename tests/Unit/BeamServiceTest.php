<?php

use superbig\beam\models\BeamModel;
use superbig\beam\services\BeamService;

it('selects csv versus xlsx filenames from the same model', function () {
    $model = new BeamModel();
    $model->setFilename('sales');

    expect($model->getFilename('csv'))->toBe('sales.csv');
    expect($model->getFilename('xlsx'))->toBe('sales.xlsx');
});

it('uses the default content path when no sheets are configured', function () {
    $model = new BeamModel([
        'header' => ['A'],
        'content' => [['1']],
    ]);

    expect($model->sheets)->toBe([]);
    expect($model->getContent())->toBe([['1']]);
});

it('uses the multi-sheet path when sheets are present', function () {
    $model = new BeamModel();
    $model->setSheets([
        [
            'name' => 'One',
            'header' => ['H'],
            'content' => [['r']],
        ],
    ]);

    expect($model->sheets)->not->toBeEmpty();
    expect($model->getContent())->toBe([]);
});

it('round-trips hashed download config', function () {
    $service = new BeamService();
    $payload = [
        'filename' => 'sales.csv',
        'tempFilename' => 'beam/abc-sales.csv',
        'mimeType' => 'text/csv',
    ];

    expect($service->unhashConfig($service->hashConfig($payload)))->toBe($payload);
});

it('exposes a stable beam temp directory prefix', function () {
    expect(BeamService::TEMP_DIR)->toBe('beam');
});
