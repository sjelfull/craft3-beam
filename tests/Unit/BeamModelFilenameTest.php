<?php

use superbig\beam\models\BeamModel;

it('does not fatal when resolving a filename on PHP 8.2+', function () {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80200);

    $model = new BeamModel();
    $model->setFilename('report');

    expect($model->getFilename('csv'))->toBe('report.csv');
});

it('appends the requested extension after sanitizing', function () {
    $model = new BeamModel(['filename' => 'export.xlsx']);

    expect($model->getFilename('csv'))->toBe('export.csv');
    expect($model->getFilename('xlsx'))->toBe('export.xlsx');
});

it('uses the default filename when none is set', function () {
    $model = new BeamModel();

    expect($model->getFilename('csv'))->toBe('output.csv');
});

it('strips ASCII control characters including DEL from filenames', function () {
    $model = new BeamModel();
    $model->setFilename("re\x00port\x1Fname\x7F");

    expect($model->getFilename('csv'))->toBe('reportname.csv');
});

it('preserves spaces in filenames', function () {
    $model = new BeamModel();
    $model->setFilename('My Report');

    expect($model->getFilename('xlsx'))->toBe('My Report.xlsx');
});
