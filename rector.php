<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\PHPUnit\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
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
        AddSeeTestAnnotationRector::class,

        /*
         * Skip selected files
         */
        __DIR__.'/tests/Session/Storage/ReadOnlyNativeSessionStorageTest.php', // Rector ends up recursing on this file
    ]);

    // AddSeeTestAnnotationRector
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
    $rectorConfig->phpstanConfig(__DIR__.'/phpstan.neon');

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        PHPUnitLevelSetList::UP_TO_PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_EXCEPTION,
        PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER,
        PHPUnitSetList::REMOVE_MOCKS,
    ]);
};