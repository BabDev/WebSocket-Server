<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->skip([
        /*
         * Skip selected rules
         */
        AddLiteralSeparatorToNumberRector::class,
        AddSeeTestAnnotationRector::class,
    ]);

    // AddSeeTestAnnotationRector
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
    $rectorConfig->phpstanConfigs([
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/phpstan.neon',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        PHPUnitLevelSetList::UP_TO_PHPUNIT_100,
    ]);
};
