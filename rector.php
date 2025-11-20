<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(level: 10)
    ->withDeadCodeLevel(level: 10)
    ->withCodeQualityLevel(level: 10)
    ->withImportNames(removeUnusedImports: true);
