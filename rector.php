<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withAttributesSets(symfony: true, phpunit: true)
    ->withImportNames(removeUnusedImports: true)
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
    ])
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        earlyReturn: true,
        codeQuality: true,
        privatization: true,
        typeDeclarations: true,
        strictBooleans: true,
    );
