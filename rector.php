<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/demo',
        __DIR__ . '/tests/Integration/var',
        // Doctrine calls postGenerateSchema() on the no-op listener: the "empty" method is required.
        RemoveEmptyClassMethodRector::class => [__DIR__ . '/tests/Integration/NoopDoctrineCacheSchemaListener.php'],
        // Properties read through reflection by DbalSchemaNameHelper tests / registry stubs.
        RemoveUnusedPrivatePropertyRector::class  => [__DIR__ . '/tests'],
        RemoveUnusedPromotedPropertyRector::class => [__DIR__ . '/tests'],
        // Optional collaborators set by #[Required] setters must keep their "= null" default (no constructor assignment).
        RemoveDefaultValueFromAssignedPropertyRector::class,
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
