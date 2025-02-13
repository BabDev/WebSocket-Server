<?php declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/phpstan.neon',
    ])
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, phpunitCodeQuality: true)
;
