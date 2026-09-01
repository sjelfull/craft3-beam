#!/bin/bash
set -e

echo "==> Installing dependencies..."
composer config --no-plugins allow-plugins.yiisoft/yii2-composer true
composer config --no-plugins allow-plugins.craftcms/plugin-installer true
composer config --no-plugins allow-plugins.pestphp/pest-plugin true
composer require "craftcms/cms:^5.0.0" --no-update --no-interaction
composer require --dev "markhuot/craft-pest-core:^3.0" --no-update --no-interaction
composer install --prefer-dist --no-progress --no-interaction

echo "==> Setting up config files..."
mkdir -p ./storage ./config/project ./tests/templates

# Copy base configs from pest-core
cp -r ./vendor/markhuot/craft-pest-core/stubs/config/app.php ./config/app.php
cp -r ./vendor/markhuot/craft-pest-core/stubs/config/general.php ./config/general.php

echo "==> Running test suite..."
./vendor/bin/pest
