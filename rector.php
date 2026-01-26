<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->skip([
        __DIR__.'/vendor',
        __DIR__.'/demo',
        __DIR__.'/coverage',
    ]);

    // PHP version
    $rectorConfig->phpVersion(80100);

    // Sets
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SymfonySetList::SYMFONY_60,
        SymfonySetList::SYMFONY_70,
        SymfonySetList::SYMFONY_80,
    ]);

    // Import names
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
};
