#!/usr/bin/env php
<?php
// HectorORM – prepare-release.php
// Unified version: Merges Unreleased into Target Version AND updates root CHANGELOG.
//
// Usage:
//   php bin/prepare-release.php 1.1.0 2025-09-19 [--dry-run]
//   php bin/prepare-release.php 1.1.0 2025-09-19 Orm Query
//
// Logic:
// 1. For each package: Merge Unreleased -> Version, collect items.
// 2. For Root: Merge (Existing Root Version + Root Unreleased + Collected Items) -> Root Version.

declare(strict_types=1);

// ------- Args & Setup -----------------------------------------------------
$args = array_values(array_slice($argv, 1));
$dryRun = false;
$filtered = [];
foreach ($args as $a) {
    if ($a === '--dry-run') $dryRun = true;
    else $filtered[] = $a;
}
$args = $filtered;

if (count($args) < 2) {
    fwrite(STDERR, "Usage: php bin/prepare-release.php <version> <date:YYYY-MM-DD> [Package1 ...] [--dry-run]\n");
    exit(1);
}

$version = $args[0];
$date = $args[1];
$explicitPkgs = array_slice($args, 2);

$rootDir = realpath(__DIR__ . '/..') ?: getcwd();
$srcDir = $rootDir . '/src';
$rootFile = $rootDir . '/CHANGELOG.md';

// Package selection
$pkgs = $explicitPkgs;
if (empty($pkgs)) {
    $pkgs = array_values(array_map('basename', array_filter(glob($srcDir . '/*'), 'is_dir')));
}

// Standard Keep a Changelog order
const CATEGORY_ORDER = ['Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security', 'Docs'];
const PLACEHOLDER = '_No changes in this release._';

// ------- Helpers ----------------------------------------------------------

function normEol(string $s): string {
    return str_replace(["\r\n", "\r"], "\n", $s);
}

// Extract the raw body text between two version headers
function extractRawSection(string $md, string $headerRegex): ?array {
    if (preg_match($headerRegex, $md, $m, PREG_OFFSET_CAPTURE)) {
        $start = (int)$m[0][1];
        $headerLen = strlen($m[0][0]);
        $bodyStart = $start + $headerLen;

        // Find end of section (next ## header or end of string)
        if (preg_match('/^##\s*\[/m', substr($md, $bodyStart), $nextM, PREG_OFFSET_CAPTURE)) {
            $len = $nextM[0][1];
        } else {
            $len = strlen($md) - $bodyStart;
        }

        return [
            'full_match' => substr($md, $start, $headerLen + $len),
            'body' => trim(substr($md, $bodyStart, $len)),
            'start' => $start,
            'length' => $headerLen + $len
        ];
    }
    return null;
}

// Parses "Keep a Changelog" format into an associative array
function parseKeepAChangelog(string $body): array {
    $sections = [];
    $current = null;
    $buf = '';

    // Map common aliases to canonical keys
    $map = [
        'added' => 'Added', 'new' => 'Added',
        'changed' => 'Changed', 'updated' => 'Changed',
        'deprecated' => 'Deprecated',
        'removed' => 'Removed',
        'fixed' => 'Fixed', 'bugfix' => 'Fixed',
        'security' => 'Security', 'docs' => 'Docs',
    ];

    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^###\s+(.+?)\s*$/', $line, $m)) {
            if ($current !== null && isset($map[$current])) {
                $sections[$map[$current]] = array_merge($sections[$map[$current]] ?? [], extractItems($buf));
            }
            $current = strtolower(trim($m[1]));
            $buf = '';
            continue;
        }
        $buf .= $line . "\n";
    }
    if ($current !== null && isset($map[$current])) {
        $sections[$map[$current]] = array_merge($sections[$map[$current]] ?? [], extractItems($buf));
    }

    if (empty($sections) && trim($body) !== '' && trim($body) !== PLACEHOLDER) {
        $items = extractItems($body);
        if (!empty($items)) $sections['Changed'] = $items;
    }

    return $sections;
}

function extractItems(string $text): array {
    $out = [];
    foreach(explode("\n", trim($text)) as $l) {
        if (preg_match('/^\s*[-*]\s+(.*)$/', $l, $matches)) {
            $out[] = trim($matches[1]);
        }
    }
    return $out;
}

// Rebuilds a Markdown body from the categorized array with explicit spacing
function rebuildBody(array $categories): string {
    $output = "";
    foreach (CATEGORY_ORDER as $cat) {
        if (empty($categories[$cat])) continue;

        $sb = "### $cat\n\n";
        foreach ($categories[$cat] as $item) {
            $sb .= "- $item\n";
        }
        // Add the subsection block + an extra newline to separate from next section
        $output .= $sb . "\n";
    }
    return trim($output);
}

// ------- Core Logic -------------------------------------------------------

echo "Preparing release $version ($date)...\n";
if ($dryRun) echo "!! DRY RUN MODE - No files will be written !!\n";

$packageBuckets = []; // New items from packages

// --- PART 1: PROCESS PACKAGES ---

foreach ($pkgs as $pkg) {
    $clPath = $srcDir . '/' . $pkg . '/CHANGELOG.md';
    if (!is_file($clPath)) continue;

    $content = normEol(file_get_contents($clPath));

    $reUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
    $reVersion    = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

    $blockUnreleased = extractRawSection($content, $reUnreleased);
    $blockVersion    = extractRawSection($content, $reVersion);

    $newPkgContent = $content;
    $finalVersionBody = '';
    $actionLog = '';

    // CASE 1: Version already exists -> MERGE
    if ($blockVersion && $blockUnreleased) {
        $unreleasedItems = parseKeepAChangelog($blockUnreleased['body']);
        $versionItems    = parseKeepAChangelog($blockVersion['body']);

        $mergedItems = array_merge_recursive($versionItems, $unreleasedItems);
        $finalVersionBody = rebuildBody($mergedItems);
        if ($finalVersionBody === '') $finalVersionBody = PLACEHOLDER;

        $newVersionBlock = "## [$version] - $date\n\n" . $finalVersionBody . "\n\n";
        $newUnreleasedBlock = "## [Unreleased]\n\n";

        // Reconstruct content
        $head = substr($content, 0, $blockUnreleased['start']);
        $between = substr($content, $blockUnreleased['start'] + $blockUnreleased['length'], $blockVersion['start'] - ($blockUnreleased['start'] + $blockUnreleased['length']));
        $tail = substr($content, $blockVersion['start'] + $blockVersion['length']);

        $newPkgContent = $head . $newUnreleasedBlock . $between . $newVersionBlock . $tail;
        $actionLog = "Merged 'Unreleased' into existing [$version]";
    }
    // CASE 2: Version does not exist -> RENAME Unreleased
    elseif ($blockUnreleased) {
        $body = $blockUnreleased['body'];
        if (trim($body) === '' || trim($body) === PLACEHOLDER) {
            $body = PLACEHOLDER;
        }
        $finalVersionBody = $body;

        $replacement = "## [Unreleased]\n\n";
        $replacement .= "## [$version] - $date\n\n";
        $replacement .= $body . "\n\n";

        $newPkgContent = substr_replace($content, $replacement, $blockUnreleased['start'], $blockUnreleased['length']);
        $actionLog = "Tagged 'Unreleased' as [$version]";
    }
    else {
        echo "  [SKIP] $pkg (No 'Unreleased' section found)\n";
        continue;
    }

    if ($newPkgContent !== $content) {
        if (!$dryRun) {
            file_put_contents($clPath, $newPkgContent);
            echo "  [OK]   $pkg: $actionLog\n";
        } else {
            echo "  [DRY]  $pkg: $actionLog\n";
        }
    }

    // Collect items for Root Aggregation
    if ($finalVersionBody !== '' && trim($finalVersionBody) !== PLACEHOLDER) {
        $cats = parseKeepAChangelog($finalVersionBody);
        $prefix = 'hectororm/' . strtolower($pkg);
        foreach ($cats as $cat => $items) {
            foreach ($items as $it) {
                // Prevent double prefixing
                if (str_starts_with($it, $prefix)) {
                    $packageBuckets[$cat][] = $it;
                } else {
                    $packageBuckets[$cat][] = "**$prefix**: " . $it;
                }
            }
        }
    }
}

// --- PART 2: UPDATE ROOT CHANGELOG ---

if (!is_file($rootFile)) {
    echo "Root CHANGELOG.md not found.\n";
    exit(0);
}

$rootContent = normEol(file_get_contents($rootFile));

// Regex for Root sections
$reRootUnreleased = '/^##\s*\[?Unreleased\]?\s*$/m';
$reRootVersion    = '/^##\s*\[' . preg_quote($version, '/') . '\]\s*(?:-\s*[\d-]{10})?\s*$/m';

$blkRootUnrel = extractRawSection($rootContent, $reRootUnreleased);
$blkRootVer   = extractRawSection($rootContent, $reRootVersion);

$existingRootItems = [];
$unreleasedRootItems = [];

if ($blkRootVer) {
    $existingRootItems = parseKeepAChangelog($blkRootVer['body']);
}
if ($blkRootUnrel) {
    $unreleasedRootItems = parseKeepAChangelog($blkRootUnrel['body']);
}

// MERGE: Existing Root Version + Root Unreleased + New Package Items
$allRootItems = array_merge_recursive($existingRootItems, $unreleasedRootItems, $packageBuckets);

// Deduplicate items (in case script runs multiple times)
foreach ($allRootItems as $cat => $list) {
    $allRootItems[$cat] = array_values(array_unique($list));
}

if (empty($allRootItems)) {
    echo "No content to update in Root CHANGELOG.\n";
    exit(0);
}

$newRootBody = rebuildBody($allRootItems);
$newRootSection = "## [$version] - $date\n\n" . $newRootBody . "\n\n";
$emptyUnreleased = "## [Unreleased]\n\n";

$newRootContent = $rootContent;
$action = "";

if ($blkRootVer && $blkRootUnrel) {
    // Update both sections
    // 1. Replace Version
    $newRootContent = substr_replace($newRootContent, $newRootSection, $blkRootVer['start'], $blkRootVer['length']);

    // Recalculate positions is risky with substr_replace sequentially on raw strings if length changes.
    // Easier strategy: Replace placeholders or reconstruct.
    // Let's reconstruct assuming Unreleased is TOP, Version is BELOW.

    $head = substr($rootContent, 0, $blkRootUnrel['start']);
    $between = substr($rootContent, $blkRootUnrel['start'] + $blkRootUnrel['length'], $blkRootVer['start'] - ($blkRootUnrel['start'] + $blkRootUnrel['length']));
    $tail = substr($rootContent, $blkRootVer['start'] + $blkRootVer['length']);

    $newRootContent = $head . $emptyUnreleased . $between . $newRootSection . $tail;
    $action = "Merged Unreleased & Existing Version into [$version]";

} elseif ($blkRootVer) {
    // Just update the version block (no unreleased found or touched)
    $newRootContent = substr_replace($rootContent, $newRootSection, $blkRootVer['start'], $blkRootVer['length']);
    $action = "Updated existing [$version] block";

} elseif ($blkRootUnrel) {
    // Standard Release: Rename Unreleased -> Version, Create new Unreleased
    $replacement = $emptyUnreleased . $newRootSection;
    $newRootContent = substr_replace($rootContent, $replacement, $blkRootUnrel['start'], $blkRootUnrel['length']);
    $action = "Created [$version] from Unreleased + Packages";

} else {
    echo "Error: Root CHANGELOG.md has no 'Unreleased' or '[$version]' section.\n";
    exit(1);
}

if ($dryRun) {
    echo "\n===== ROOT PREVIEW ($action) =====\n" . $newRootSection . "==================================\n";
} else {
    file_put_contents($rootFile, $newRootContent);
    echo "Root CHANGELOG processed: $action.\n";
}

echo "Done.\n";
