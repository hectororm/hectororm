#!/usr/bin/env php
<?php
// HectorORM – prepare-package-release: tag a single package CHANGELOG (simplified)
//
// Usage:
//   php bin/prepare-package-release.php Orm 1.1.0 [2025-09-19] [--dry-run]
//
// Behaviour:
// - Uses the folder name under src/ exactly as provided (no composer lookup, no scanning).
// - Reads src/<Package>/CHANGELOG.md. If absent, exits with code 2.
// - Finds the block beginning at "## [Unreleased]" and REPLACES it with:
//       ## [Unreleased]
//
//       ## [<version>] - <date>
//
//       <previous Unreleased content OR placeholder>
// - Leaves any text above Unreleased intact (notes, intro).
// - Returns code 0 on success, 2 on not found/skip, 1 invalid usage.

declare(strict_types=1);

$args = array_values(array_slice($argv, 1));
if (count($args) < 2) {
    fwrite(STDERR, "Usage: php bin/prepare-package-release.php <package-folder> <version> [date] [--dry-run]\n");
    exit(1);
}

$pkgFolder = $args[0];
$version = $args[1];
$releaseDate = $args[2] ?? (new DateTimeImmutable('now'))->format('Y-m-d');
$dryRun = in_array('--dry-run', $args, true);

$placeholder = '_No changes in this release._';

$root = realpath(__DIR__ . '/..') ?: getcwd();
$packagesDir = $root . '/src';
$target = $packagesDir . '/' . $pkgFolder;

if (!is_dir($target)) {
    fwrite(STDERR, "Package folder not found under src: $pkgFolder\n");
    exit(2);
}

$clPath = $target . '/CHANGELOG.md';
if (!is_file($clPath)) {
    fwrite(STDERR, "CHANGELOG not found: $clPath\n");
    exit(2);
}

function readFileOr(string $path, string $fallback = ''): string
{
    return is_file($path) ? (string)file_get_contents($path) : $fallback;
}

function writeFile(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Cannot write $path");
    }
}

function normalizeEol(string $s): string
{
    return str_replace(["\r\n", "\r"], "\n", $s);
}

function replaceUnreleasedBlock(string $md, string $version, string $date, string $placeholder): array
{
    $md = normalizeEol($md);
    if (!preg_match('/^##\s*\[?Unreleased\]?\s*\n(.*?)(?=^##\s*\[|\z)/ims', $md, $m, PREG_OFFSET_CAPTURE)) {
        return [$md, false];
    }
    $body = trim($m[1][0]);
    $hadReal = ($body !== '');
    if ($body === '') {
        $body = $placeholder;
    }

    $replacement = "## [Unreleased]\n\n";
    $replacement .= "## [$version] - $date\n\n";
    $replacement .= $body . "\n\n";

    $start = (int)$m[0][1];
    $len = (int)strlen($m[0][0]);
    $newMd = substr($md, 0, $start) . $replacement . substr($md, $start + $len);
    return [$newMd, $hadReal];
}

$md = readFileOr($clPath);
[$newMd, $hadReal] = replaceUnreleasedBlock($md, $version, $releaseDate, $placeholder);

if ($dryRun) {
    fwrite(STDOUT, "[DRY] $clPath -> [$version] - $releaseDate (" . ($hadReal ? 'changes' : 'no changes') . ")\n");
} else {
    writeFile($clPath, $newMd);
    fwrite(STDOUT, "Updated $clPath -> [$version] - $releaseDate\n");
}

exit(0);
