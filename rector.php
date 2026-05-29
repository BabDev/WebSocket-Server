<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        ThrowWithPreviousExceptionRector::class, // Forces re-throwing with the wrapped exception's code, we aren't interested in this
    ])
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/phpstan.neon',
    ])
    ->withComposerBased(phpunit: true)
    ->withPhpSets()
    ->withPreparedSets(codeQuality: true, phpunitCodeQuality: true)
;
