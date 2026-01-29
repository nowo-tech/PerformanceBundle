#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Validates YAML files in the translations directory.
 *
 * - Checks that each file is valid YAML (parseable).
 * - Detects duplicate keys at the same level (YAML allows them but the last wins; often a mistake).
 *
 * Usage:
 *   php scripts/validate-translations-yaml.php [dir]
 *
 * Default dir: src/Resources/translations
 *
 * Exit code: 0 if all valid, 1 if any error.
 */
$translationsDir = $argv[1] ?? __DIR__.'/../src/Resources/translations';

if (!is_dir($translationsDir)) {
    fwrite(\STDERR, "Error: Directory not found: {$translationsDir}\n");
    exit(1);
}

$files = glob($translationsDir.'/*.yaml');
if (false === $files || [] === $files) {
    fwrite(\STDERR, "Error: No YAML files found in {$translationsDir}\n");
    exit(1);
}

$hasErrors = false;

foreach ($files as $file) {
    $basename = basename($file);
    $content = @file_get_contents($file);
    if (false === $content) {
        fwrite(\STDERR, "{$basename}: Could not read file.\n");
        $hasErrors = true;
        continue;
    }

    // 1. Syntax: try to parse with ext-yaml if available
    if (function_exists('yaml_parse')) {
        $data = @yaml_parse($content);
        if (false === $data && '' !== substr(trim($content), 0, 1)) {
            fwrite(\STDERR, "{$basename}: Invalid YAML syntax.\n");
            $hasErrors = true;
            continue;
        }
    }
    // If ext-yaml is not installed, only duplicate-key check runs (syntax not validated)

    // 2. Duplicate keys: scan lines and track keys per indent level
    $duplicates = findDuplicateKeys($content);
    if ([] !== $duplicates) {
        foreach ($duplicates as $dup) {
            fwrite(\STDERR, "{$basename}:{$dup['line']}: Duplicate key \"{$dup['key']}\" at same level.\n");
        }
        $hasErrors = true;
    }
}

if ($hasErrors) {
    exit(1);
}

echo "All translation YAML files are valid (no syntax errors, no duplicate keys).\n";
exit(0);

/**
 * Find duplicate keys in YAML content (same key at same indent level).
 *
 * @return list<array{line: int, key: string}>
 */
function findDuplicateKeys(string $content): array
{
    $duplicates = [];
    $lines = explode("\n", $content);
    /** @var array<int, array<string, true>> keys seen per indent level */
    $keysByIndent = [];

    foreach ($lines as $zeroBased => $line) {
        $lineNum = $zeroBased + 1;

        // Blank or comment
        $trimmed = ltrim($line, " \t");
        if ('' === $trimmed || '#' === $trimmed[0]) {
            continue;
        }

        $indent = strlen($line) - strlen($trimmed);

        // Multi-line value continuation (line that doesn't look like a new key)
        if (preg_match('#^\s+\S#', $line) && !preg_match('#^\s*[^\s:]+:\s*#', $line) && !preg_match('#^\s*[^\s:]+:\s*\S#', $line)) {
            $prevIndent = null;
            foreach (array_keys($keysByIndent) as $i) {
                if ($i < $indent) {
                    $prevIndent = $i;
                }
            }
            if (null !== $prevIndent && $indent > $prevIndent) {
                continue; // continuation of a multi-line value
            }
        }

        // Match "key:" or "key: value" (key = unquoted identifier, possibly with dots for nested in one line we don't handle)
        if (!preg_match('#^(\s*)([a-zA-Z0-9_]+):\s*(.*)$#', $line, $m)) {
            continue;
        }

        $key = $m[2];

        // When going to a lower indent, forget deeper levels
        foreach (array_keys($keysByIndent) as $i) {
            if ($i > $indent) {
                unset($keysByIndent[$i]);
            }
        }

        if (isset($keysByIndent[$indent][$key])) {
            $duplicates[] = ['line' => $lineNum, 'key' => $key];
        } else {
            if (!isset($keysByIndent[$indent])) {
                $keysByIndent[$indent] = [];
            }
            $keysByIndent[$indent][$key] = true;
        }
    }

    return $duplicates;
}
