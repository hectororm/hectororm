#!/usr/bin/env php
<?php
// HectorORM – prepare-changelog (simplified + dry-run)
// Build/replace a root CHANGELOG entry for a given version from package changelogs
//
// Usage (packages optional; default = all folders under src/):
//   php bin/prepare-changelog.php 1.1.0 2025-09-19 Orm Query --dry-run
//   php bin/prepare-changelog.php 1.1.0 2025-09-19                --dry-run
//   php bin/prepare-changelog.php 1.1.0 2025-09-19 Orm Query
//
// Behaviour
// - Reads src/<Pkg>/CHANGELOG.md for each selected package.
// - Extracts the body of "## [<version>] - <date>" (date optional in source).
// - Parses Keep a Changelog subsections (### Added/Changed/Deprecated/Removed/Fixed/Security/Docs/Meta/Breaking).
// - Aggregates bullets into the root CHANGELOG under:
//       ## [<version>] - <date>
//
//       ### Added
//       - hectororm/<pkg>: ...
//
// - If no root CHANGELOG.md, the script does nothing.
// - If no "Unreleased" section in root, the script does nothing.
// - Skips packages missing CHANGELOG.md or whose body is exactly "_No changes in this release._".
// - --dry-run prints the section preview and does not write files.

declare(strict_types=1);

// ------- Args -------------------------------------------------------------
$args = array_values(array_slice($argv, 1));
$dryRun = false;
$filtered = [];
foreach ($args as $a) {
    if ($a === '--dry-run') {
        $dryRun = true;
    } else {
        $filtered[] = $a;
    }
}
$args = $filtered;

if (count($args) < 2) {
    fwrite(STDERR,
        "Usage: php bin/prepare-changelog.php <version> <date:YYYY-MM-DD> [Package1 Package2 ...] [--dry-run]\n");
    exit(1);
}
$version = $args[0];
$date = $args[1];
$pkgs = array_slice($args, 2);

// ------- Paths ------------------------------------------------------------
$rootDir = realpath(__DIR__ . '/..') ?: getcwd();
$srcDir = $rootDir . '/src';
$rootFile = $rootDir . '/CHANGELOG.md';
if (!is_dir($srcDir)) {
    fwrite(STDERR, "src directory not found: $srcDir\n");
    exit(2);
}
if (!is_file($rootFile)) {
    fwrite(STDOUT, "Root CHANGELOG.md not found, skipping.\n");
    exit(0);
}

if (empty($pkgs)) {
    $pkgs = array_values(array_map('basename', array_filter(glob($srcDir . '/*'), 'is_dir')));
}

// ------- Helpers ----------------------------------------------------------
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

function normEol(string $s): string
{
    return str_replace(["\r\n", "\r"], "\n", $s);
}

function extractVersionBody(string $md, string $version): string
{
    $md = normEol($md);
    if (preg_match('/^##\s*\[' . preg_quote($version,
            '/') . '\]\s*(?:-\s*\d{4}-\d{2}-\d{2})?\s*\n(.*?)(?=^##\s*\[|\z)/ims', $md, $m)) {
        return trim($m[1]);
    }
    return '';
}

function extractItems(string $text): array
{
    $text = trim(normEol($text));
    if ($text === '') {
        return [];
    }
    $out = [];
    $buf = '';
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
            if ($buf !== '') {
                $out[] = trim($buf);
                $buf = '';
            }
            $out[] = trim($m[1]);
        } elseif (trim($line) === '') {
            if ($buf !== '') {
                $out[] = trim($buf);
                $buf = '';
            }
        } else {
            $buf .= ($buf === '' ? '' : ' ') . trim($line);
        }
    }
    if ($buf !== '') {
        $out[] = trim($buf);
    }
    return array_values(array_filter($out, fn($s) => $s !== ''));
}

function parseKeepAChangelog(string $body): array
{
    $body = normEol($body);
    $sections = [];
    $current = null;
    $buf = '';
    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^###\s+(.+?)\s*$/', $line, $m)) {
            if ($current !== null) {
                $sections[$current] = ($sections[$current] ?? '') . "\n" . trim($buf);
                $buf = '';
            }
            $current = strtolower(trim($m[1]));
            continue;
        }
        $buf .= $line . "\n";
    }
    if ($current !== null) {
        $sections[$current] = ($sections[$current] ?? '') . "\n" . trim($buf);
    }
    $map = [
        'added' => 'Added',
        'new' => 'Added',
        'changed' => 'Changed',
        'update' => 'Changed',
        'updated' => 'Changed',
        'deprecated' => 'Deprecated',
        'removed' => 'Removed',
        'deleted' => 'Removed',
        'fixed' => 'Fixed',
        'bugfix' => 'Fixed',
        'bug fixes' => 'Fixed',
        'security' => 'Security',
        'docs' => 'Docs',
        'documentation' => 'Docs',
        'breaking' => 'Breaking',
        'breaking changes' => 'Breaking',
        'meta' => 'Meta',
        'chore' => 'Meta',
    ];
    $out = [];
    foreach ($sections as $k => $text) {
        $canon = $map[$k] ?? null;
        if ($canon === null) {
            continue;
        }
        $items = extractItems($text);
        if (!empty($items)) {
            $out[$canon] = array_merge($out[$canon] ?? [], $items);
        }
    }
    if (empty($out) && trim($body) !== '') {
        $fallback = extractItems($body);
        $out['Changed'] = !empty($fallback) ? $fallback : [trim($body)];
    }
    return $out;
}

function insertOrReplaceRootVersion(string $rootMd, string $version, string $date, string $section): ?string
{
    $rootMd = normEol($rootMd);
    if (!preg_match('/^##\s*\[?Unreleased\]?/mi', $rootMd)) {
        return null; // no unreleased zone
    }
    $pattern = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*-?\s*' . preg_quote($date,
            '/') . '\s*\n(.*?)(?=^##\s*\[|\z)/ims';
    if (preg_match($pattern, $rootMd)) {
        return preg_replace($pattern, $section, $rootMd);
    }
    if (preg_match('/^##\s*\[?Unreleased\]?\s*\n(.*?)(?=^##\s*\[|\z)/ims', $rootMd, $m, PREG_OFFSET_CAPTURE)) {
        $insertPos = (int)$m[0][1] + strlen($m[0][0]);
        $before = substr($rootMd, 0, $insertPos);
        if (preg_match('/\n\n$/', $before)) {
            return $before . $section . substr($rootMd, $insertPos);
        }
        return $before . "\n" . $section . substr($rootMd, $insertPos);
    }
    return null;
}

// ------- Collect ---------------------------------------------------------
$categoryOrder = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security', 'Docs', 'Meta', 'Breaking'];
$buckets = [];
foreach ($pkgs as $pkg) {
    $cl = $srcDir . '/' . $pkg . '/CHANGELOG.md';
    if (!is_file($cl)) {
        continue;
    }
    $body = extractVersionBody(readFileOr($cl), $version);
    if ($body === '' || trim($body) === '_No changes in this release._') {
        continue;
    }
    $cats = parseKeepAChangelog($body);
    $prefix = 'hectororm/' . strtolower($pkg);
    foreach ($cats as $cat => $items) {
        foreach ($items as $it) {
            $buckets[$cat][] = $prefix . ': ' . $it;
        }
    }
}

if (empty($buckets)) {
    fwrite(STDOUT, "Nothing to aggregate for version $version.\n");
    exit(0);
}

// ------- Build section ---------------------------------------------------
$lines = [];
$lines[] = '## [' . $version . '] - ' . $date;
$lines[] = '';
foreach ($categoryOrder as $cat) {
    if (empty($buckets[$cat])) {
        continue;
    }
    $lines[] = '### ' . $cat;
    $lines[] = '';
    foreach ($buckets[$cat] as $it) {
        $lines[] = '- ' . $it;
    }
    $lines[] = '';
}
$section = rtrim(implode("\n", $lines)) . "\n\n";

// ------- Write or preview root -----------------------------------------
$rootMd = readFileOr($rootFile);
$updated = insertOrReplaceRootVersion($rootMd, $version, $date, $section);
if ($updated === null) {
    fwrite(STDOUT, "No Unreleased section found, skipping.\n");
    exit(0);
}

if ($dryRun) {
    fwrite(STDOUT, "===== ROOT PREVIEW [" . $version . ' - ' . $date . "] =====\n\n");
    fwrite(STDOUT, $section);
    exit(0);
}

writeFile($rootFile, $updated);
fwrite(STDOUT, "Root CHANGELOG updated for $version.\n");
