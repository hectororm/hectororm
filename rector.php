<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withConfiguredRule(RenameMethodRector::class, [
        new MethodCallRename(SplObjectStorage::class, 'contains', 'offsetExists'),
        new MethodCallRename(SplObjectStorage::class, 'attach', 'offsetSet'),
        new MethodCallRename(SplObjectStorage::class, 'detach', 'offsetUnset'),
    ])
    ->withTypeCoverageLevel(level: 10)
    ->withDeadCodeLevel(level: 10)
    ->withCodeQualityLevel(level: 10)
    ->withImportNames(removeUnusedImports: true);
