#!/usr/bin/env php
<?php
// HectorORM – prepare-release.php
// Unified version: Merges Unreleased into Target Version AND updates root CHANGELOG.
//
// Usage:
//   php bin/prepare-release.php 1.1.0 2025-09-19 [--dry-run]
//   php bin/prepare-release.php 1.1.0 2025-09-19 Orm Query
//
// Logic per package:
// 1. Checks if target version (e.g. 1.1.0) already exists in CHANGELOG.md.
// 2. If EXISTS: Merges "Unreleased" items into existing 1.1.0 items, then clears "Unreleased".
// 3. If NOT EXISTS: Renames "Unreleased" to 1.1.0 and creates a new empty "Unreleased".
// 4. Aggregates the final content of 1.1.0 into the root CHANGELOG.md with proper spacing.

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

    // Remove the very last newline to avoid double spacing at end of file/block
    return trim($output);
}

// ------- Core Logic -------------------------------------------------------

echo "Preparing release $version ($date)...\n";
if ($dryRun) echo "!! DRY RUN MODE - No files will be written !!\n";

$buckets = []; // For Root Changelog aggregation

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

        // Empty Unreleased block (no placeholder)
        $newUnreleasedBlock = "## [Unreleased]\n\n";

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

        // Empty Unreleased block (no placeholder)
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
    } else {
        echo "  [SKIP] $pkg (No content changes detected)\n";
    }

    // AGGREGATION Logic
    if ($finalVersionBody === '' || trim($finalVersionBody) === PLACEHOLDER) {
        continue;
    }

    $cats = parseKeepAChangelog($finalVersionBody);
    $prefix = 'hectororm/' . strtolower($pkg);

    foreach ($cats as $cat => $items) {
        foreach ($items as $it) {
            if (str_starts_with($it, $prefix)) {
                $buckets[$cat][] = $it;
            } else {
                $buckets[$cat][] = "**$prefix**: " . $it;
            }
        }
    }
}

// ------- Root Changelog Update --------------------------------------------

if (empty($buckets)) {
    echo "No changes found to aggregate in root CHANGELOG.\n";
    exit(0);
}

// Rebuild with explicit double spacing logic
$newRootSection = "## [$version] - $date\n\n" . rebuildBody($buckets) . "\n\n";

$rootContent = is_file($rootFile) ? normEol(file_get_contents($rootFile)) : '';

if (preg_match('/^##\s*\[' . preg_quote($version, '/') . '\]/m', $rootContent)) {
    $newRootContent = preg_replace(
        '/^##\s*\[' . preg_quote($version, '/') . '\].*?(?=^##\s*\[|\z)/ims',
        $newRootSection,
        $rootContent,
        1
    );
    $action = "Updated existing root section";
}
elseif (preg_match('/^##\s*\[?Unreleased\]?\s*\n(.*?)(?=^##\s*\[|\z)/ims', $rootContent, $m, PREG_OFFSET_CAPTURE)) {
    $insertPos = (int)$m[0][1] + strlen($m[0][0]);
    // Ensure we have breathing room before the insertion if needed
    if (!str_ends_with(substr($rootContent, 0, $insertPos), "\n\n")) {
        $newRootSection = "\n" . $newRootSection;
    }
    $newRootContent = substr($rootContent, 0, $insertPos) . $newRootSection . substr($rootContent, $insertPos);
    $action = "Added new root section";
} else {
    echo "Error: Root CHANGELOG.md has no 'Unreleased' section.\n";
    exit(1);
}

if ($dryRun) {
    echo "\n===== ROOT PREVIEW ($action) =====\n" . $newRootSection . "==================================\n";
} else {
    file_put_contents($rootFile, $newRootContent);
    echo "Root CHANGELOG processed: $action.\n";
}

echo "Done.\n";
