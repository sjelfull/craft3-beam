<?php

use superbig\beam\models\BeamModel;
use superbig\beam\services\BeamService;

it('creates a model from config via the service', function () {
    $service = new BeamService();
    $model = $service->create([
        'filename' => 'orders',
        'header' => ['Name', 'Total'],
        'content' => [
            ['Ada', '10'],
        ],
    ]);

    expect($model)->toBeInstanceOf(BeamModel::class);
    expect($model->filename)->toBe('orders');
    expect($model->getConfig())->toBe([
        'header' => ['Name', 'Total'],
        'rows' => [
            ['Ada', '10'],
        ],
    ]);
});

it('copies rows into content on init', function () {
    $model = new BeamModel([
        'rows' => [
            ['one'],
        ],
    ]);

    expect($model->getContent())->toBe([['one']]);
});

it('appends rows to default content', function () {
    $model = new BeamModel();
    $model->setHeader(['A']);
    $model->append(['first']);
    $model->append(['second']);

    expect($model->header)->toBe(['A']);
    expect($model->getContent())->toBe([
        ['first'],
        ['second'],
    ]);
});

it('routes header and content onto a named sheet', function () {
    $model = new BeamModel();
    $model->sheet('Summary', [
        'header' => ['Col'],
        'content' => [['a']],
    ]);
    $model->append(['b']);

    expect($model->sheets)->toHaveCount(1);
    expect($model->sheets[0]['name'])->toBe('Summary');
    expect($model->sheets[0]['header'])->toBe(['Col']);
    expect($model->sheets[0]['content'])->toBe([['a'], ['b']]);
    expect($model->getContent())->toBe([]);
});
