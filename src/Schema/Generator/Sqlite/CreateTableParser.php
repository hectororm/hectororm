<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema\Generator\Sqlite;

use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Exception\PlanException;

/**
 * Extract generated expressions and CHECKs, and rewrite column-reference tokens.
 *
 * @internal
 */
final class CreateTableParser
{
    /**
     * Rename identifier references while retaining literal strings and comments.
     * Function names, qualifiers, COLLATE names and CAST types are not references.
     *
     * Ambiguous unquoted keywords must be renamed through SQLite's native ALTER
     * in a separate plan rather than guessed by this limited expression parser.
     *
     * @throws PlanException
     */
    public function renameColumnReferences(string $expression, string $oldName, string $newName): string
    {
        $tokens = $this->tokenize($expression);
        $depth = 0;
        $casts = [];
        $castTypeDepth = null;
        $offset = 0;
        $result = '';

        foreach ($tokens as $index => $token) {
            if ($this->isToken($token, '(')) {
                $depth++;
                $casts[$depth] = isset($tokens[$index - 1]) && $this->isToken($tokens[$index - 1], 'CAST');
                continue;
            }

            if ($this->isToken($token, ')')) {
                if ($castTypeDepth === $depth) {
                    $castTypeDepth = null;
                }
                unset($casts[$depth]);
                $depth--;
                continue;
            }

            if (null !== $castTypeDepth) {
                continue;
            }

            if (($casts[$depth] ?? false) && $this->isToken($token, 'AS')) {
                $castTypeDepth = $depth;
                continue;
            }

            if (0 !== strcasecmp($token['text'], $oldName)) {
                continue;
            }

            if ("'" === $expression[$token['start']]) {
                continue;
            }

            if (false === $token['quoted'] && $this->isToken($token, 'X') &&
                isset($tokens[$index + 1]) && $token['end'] === $tokens[$index + 1]['start'] &&
                "'" === $expression[$tokens[$index + 1]['start']]) {
                // X'ABCD' is a blob literal, not a reference to a column named x.
                continue;
            }

            if (isset($tokens[$index + 1]) && $this->isToken($tokens[$index + 1], '(')) {
                continue;
            }

            if (isset($tokens[$index + 1]) && $this->isToken($tokens[$index + 1], '.')) {
                continue;
            }

            if (isset($tokens[$index - 1]) && $this->isToken($tokens[$index - 1], 'COLLATE')) {
                continue;
            }

            if (false === $token['quoted']) {
                if (1 !== preg_match('/^[a-zA-Z_\x80-\xff]/', $token['text'])) {
                    continue;
                }

                if (in_array(strtoupper($token['text']), [
                    'AS', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'CAST', 'COLLATE',
                    'AND', 'OR', 'NOT', 'IS', 'NULL', 'TRUE', 'FALSE', 'IN', 'BETWEEN',
                    'LIKE', 'GLOB', 'MATCH', 'REGEXP', 'ESCAPE', 'ISNULL', 'NOTNULL',
                    'DISTINCT', 'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP',
                ], true)) {
                    throw new PlanException(sprintf(
                        'Cannot safely rewrite unquoted keyword "%s" in a SQL expression; ' .
                        'use a separate native SQLite rename before rebuilding',
                        $oldName,
                    ));
                }
            }

            $result .= substr($expression, $offset, $token['start'] - $offset);
            $result .= '"' . str_replace('"', '""', $newName) . '"';
            $offset = $token['end'];
        }

        return $result . substr($expression, $offset);
    }

    /**
     * @return array<string, string> Column name => verbatim expression inside AS (...)
     * @throws SchemaException
     */
    public function getGeneratedExpressions(string $sql): array
    {
        $expressions = [];
        foreach ($this->getDefinitions($this->tokenize($sql)) as $tokens) {
            $expressions += $this->parseColumn($sql, $tokens);
        }

        return $expressions;
    }

    /**
     * Extract named and unnamed column/table CHECKs without changing expressions.
     *
     * Virtual table declarations belong to their module, not the ordinary table grammar.
     *
     * @return array<array{name: ?string, expression: string}>|null Null for virtual tables.
     * @throws SchemaException
     */
    public function getChecks(string $sql): ?array
    {
        $tokens = $this->tokenize($sql);
        $index = 1;
        if (isset($tokens[$index]) &&
            ($this->isToken($tokens[$index], 'TEMP') || $this->isToken($tokens[$index], 'TEMPORARY'))) {
            $index++;
        }
        if (isset($tokens[$index]) && $this->isToken($tokens[$index], 'VIRTUAL')) {
            return null;
        }

        $checks = [];
        foreach ($this->getDefinitions($tokens) as $definition) {
            foreach ($this->parseChecks($sql, $definition) as $check) {
                $checks[] = ['name' => $check['name'], 'expression' => $check['expression']];
            }
        }

        return $checks;
    }

    /**
     * Keep the original column/table definitions, replace CHECKs, and rename the new table.
     *
     * @param string[] $checks Compiled CHECK fragments, including any CONSTRAINT name.
     */
    public function rebuildWithChecks(string $sql, string $tableName, array $checks): string
    {
        $tokens = $this->tokenize($sql);
        $definitions = [];
        foreach ($this->getDefinitions($tokens) as $definition) {
            if ([] === $definition) {
                throw new PlanException('Cannot rebuild an empty SQLite table definition');
            }
            $ranges = $this->parseChecks($sql, $definition);
            if ([] !== $ranges && false === $this->isColumnDefinition($definition)) {
                continue;
            }
            $offset = $definition[0]['start'];
            $text = '';
            foreach ($ranges as $range) {
                $text .= substr($sql, $offset, $range['start'] - $offset);
                $offset = $range['end'];
            }
            $text .= substr($sql, $offset, $definition[array_key_last($definition)]['end'] - $offset);
            $definitions[] = trim($text);
        }

        return sprintf(
            "CREATE TABLE \"%s\" (\n  %s\n)%s",
            str_replace('"', '""', $tableName),
            implode(",\n  ", array_merge($definitions, $checks)),
            substr($sql, $this->getBodyEnd($tokens)),
        );
    }

    /** @return array<string, string> Original SQLite column type declarations. */
    public function getColumnTypes(string $sql): array
    {
        $types = [];
        foreach ($this->getDefinitions($this->tokenize($sql)) as $definition) {
            if (false === $this->isColumnDefinition($definition)) {
                continue;
            }
            $start = $end = $definition[0]['end'];
            $depth = 0;
            foreach (array_slice($definition, 1) as $token) {
                if (0 === $depth && false === $token['quoted'] && in_array(strtoupper($token['text']), [
                    'CONSTRAINT', 'PRIMARY', 'NOT', 'NULL', 'UNIQUE', 'CHECK', 'DEFAULT',
                    'COLLATE', 'REFERENCES', 'GENERATED', 'AS',
                ], true)) {
                    break;
                }
                $end = $token['end'];
                if ($this->isToken($token, '(')) {
                    $depth++;
                } elseif ($this->isToken($token, ')')) {
                    $depth--;
                }
            }
            $types[$definition[0]['text']] = trim(substr($sql, $start, $end - $start));
        }

        return $types;
    }

    /**
     * Features not represented by the structured column/index/FK diff.
     * The source-preserving CHECK-only path can retain these unchanged.
     *
     * @return string[]
     */
    public function getUnsupportedRebuildFeatures(string $sql): array
    {
        $features = [];
        $types = $this->getColumnTypes($sql);
        foreach ($this->getDefinitions($this->tokenize($sql)) as $definition) {
            $column = $this->isColumnDefinition($definition);
            $depth = 0;
            foreach ($definition as $index => $token) {
                if ($this->isToken($token, '(')) {
                    $depth++;
                } elseif ($this->isToken($token, ')')) {
                    $depth--;
                }
                if (false === $column && in_array(strtoupper($definition[0]['text']), ['UNIQUE', 'PRIMARY'], true) &&
                    false === $token['quoted'] && in_array(strtoupper($token['text']), ['COLLATE', 'ASC', 'DESC'], true)) {
                    $features[] = strtoupper($token['text']);
                }
                if (0 !== $depth || $token['quoted']) {
                    continue;
                }
                $keyword = strtoupper($token['text']);
                if (in_array($keyword, ['COLLATE', 'CONFLICT', 'DEFERRABLE', 'INITIALLY', 'MATCH', 'ASC', 'DESC'], true)) {
                    $features[] = $keyword;
                }
                if ('CONSTRAINT' === $keyword && isset($definition[$index + 2]) &&
                    false === $this->isToken($definition[$index + 2], 'CHECK') &&
                    false === $this->isToken($definition[$index + 2], 'FOREIGN')) {
                    $features[] = 'named non-CHECK constraints';
                }
                if ('PRIMARY' === $keyword &&
                    (false === $column || 'INTEGER' !== strtoupper($types[$definition[0]['text']] ?? ''))) {
                    $features[] = 'non-inline INTEGER PRIMARY KEY';
                }
            }
        }
        foreach (['STRICT', 'WITHOUT'] as $option) {
            if ($this->hasTableOption($sql, $option)) {
                $features[] = $option;
            }
        }

        return array_values(array_unique($features));
    }

    public function hasTableOption(string $sql, string $option): bool
    {
        $tokens = $this->tokenize($sql);
        $end = $this->getBodyEnd($tokens);
        foreach ($tokens as $token) {
            if ($token['start'] >= $end && $this->isToken($token, $option)) {
                return true;
            }
        }

        return false;
    }

    /** Conservative identifier detection for dependencies in views. */
    public function containsIdentifier(string $sql, string $name): bool
    {
        foreach ($this->tokenize($sql) as $token) {
            if ("'" !== $sql[$token['start']] && 0 === strcasecmp($token['text'], $name)) {
                return true;
            }
        }

        return false;
    }

    public function containsKeyword(string $sql, string $keyword): bool
    {
        foreach ($this->tokenize($sql) as $token) {
            if ($this->isToken($token, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /** Whether an explicit index can be reconstructed from a simple column list. */
    public function isSimpleIndex(string $sql): bool
    {
        $tokens = $this->tokenize($sql);
        foreach ($tokens as $index => $token) {
            if (false === $this->isToken($token, 'ON')) {
                continue;
            }
            $index += 2; // ON table
            if (isset($tokens[$index]) && $this->isToken($tokens[$index], '.')) {
                $index += 2;
            }
            if (false === isset($tokens[$index]) || false === $this->isToken($tokens[$index], '(')) {
                return false;
            }
            $expectColumn = true;
            for ($index++; $index < count($tokens); $index++) {
                $token = $tokens[$index];
                if ($this->isToken($token, ')')) {
                    return false === $expectColumn &&
                        (false === isset($tokens[$index + 1]) || $this->isToken($tokens[$index + 1], ';'));
                }
                if ($expectColumn) {
                    if ("'" === $sql[$token['start']] ||
                        (false === $token['quoted'] && 1 !== preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $token['text']))) {
                        return false;
                    }
                } elseif (false === $this->isToken($token, ',')) {
                    return false;
                }
                $expectColumn = !$expectColumn;
            }
        }

        return false;
    }

    /** @param array<array{text: string, start: int, end: int, quoted: bool}> $tokens */
    private function isColumnDefinition(array $tokens): bool
    {
        return [] !== $tokens && ($tokens[0]['quoted'] || false === in_array(
            strtoupper($tokens[0]['text']), ['CONSTRAINT', 'PRIMARY', 'UNIQUE', 'CHECK', 'FOREIGN'], true,
        ));
    }

    /** @param array<array{text: string, start: int, end: int, quoted: bool}> $tokens */
    private function getBodyEnd(array $tokens): int
    {
        $depth = 0;
        foreach ($tokens as $token) {
            if ($this->isToken($token, '(')) {
                $depth++;
            } elseif ($this->isToken($token, ')') && 0 === --$depth) {
                return $token['end'];
            }
        }

        throw new SchemaException('Cannot find the end of SQLite CREATE TABLE definitions');
    }

    /**
     * @param array<array{text: string, start: int, end: int, quoted: bool}> $tokens
     * @return array<array<array{text: string, start: int, end: int, quoted: bool}>>
     */
    private function getDefinitions(array $tokens): array
    {
        $index = 0;
        if (false === isset($tokens[$index]) || false === $this->isToken($tokens[$index], 'CREATE')) {
            throw new SchemaException('Expected SQLite CREATE TABLE statement');
        }
        $index++;
        if (isset($tokens[$index]) &&
            ($this->isToken($tokens[$index], 'TEMP') || $this->isToken($tokens[$index], 'TEMPORARY'))) {
            $index++;
        }
        if (false === isset($tokens[$index]) || false === $this->isToken($tokens[$index], 'TABLE')) {
            throw new SchemaException('Expected SQLite CREATE TABLE statement');
        }
        $index++;
        if (isset($tokens[$index]) && $this->isToken($tokens[$index], 'IF')) {
            if (false === isset($tokens[$index + 2]) ||
                false === $this->isToken($tokens[$index + 1], 'NOT') ||
                false === $this->isToken($tokens[$index + 2], 'EXISTS')) {
                throw new SchemaException('Expected SQLite IF NOT EXISTS clause');
            }
            $index += 3;
        }
        $index++; // Table name.
        if (isset($tokens[$index]) && $this->isToken($tokens[$index], '.')) {
            $index += 2; // Schema-qualified table name.
        }
        if (false === isset($tokens[$index]) || false === $this->isToken($tokens[$index], '(')) {
            throw new SchemaException('Cannot parse SQLite CREATE TABLE definitions');
        }

        $depth = 0;
        $start = null;
        $definitions = [];

        for (; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($this->isToken($token, '(')) {
                if (0 === $depth) {
                    $start = $index + 1;
                }
                $depth++;
                continue;
            }

            if ($this->isToken($token, ')')) {
                $depth--;
                if (0 === $depth && null !== $start) {
                    $definitions[] = array_slice($tokens, $start, $index - $start);

                    return $definitions;
                }
                if (0 > $depth) {
                    break;
                }
                continue;
            }

            if (1 === $depth && $this->isToken($token, ',') && null !== $start) {
                $definitions[] = array_slice($tokens, $start, $index - $start);
                $start = $index + 1;
            }
        }

        throw new SchemaException('Cannot parse SQLite CREATE TABLE definitions');
    }

    /**
     * @param array<array{text: string, start: int, end: int, quoted: bool}> $tokens
     * @return array<array{name: ?string, expression: string, start: int, end: int}>
     */
    private function parseChecks(string $sql, array $tokens): array
    {
        $checks = [];
        $depth = 0;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($this->isToken($token, '(')) {
                $depth++;
                continue;
            }
            if ($this->isToken($token, ')')) {
                $depth--;
                continue;
            }
            if (0 !== $depth) {
                continue;
            }
            if ($this->isToken($token, 'CONSTRAINT')) {
                $index++; // A constraint's name is not a constraint keyword.
                continue;
            }
            if (false === $this->isToken($token, 'CHECK')) {
                continue;
            }

            $name = 2 <= $index && $this->isToken($tokens[$index - 2], 'CONSTRAINT')
                ? $tokens[$index - 1]['text'] : null;
            $open = $index + 1;
            if (false === isset($tokens[$open]) || false === $this->isToken($tokens[$open], '(')) {
                throw new SchemaException('Missing parenthesized SQLite CHECK expression');
            }

            $depth = 1;
            for ($index = $open + 1; $index < count($tokens); $index++) {
                if ($this->isToken($tokens[$index], '(')) {
                    $depth++;
                } elseif ($this->isToken($tokens[$index], ')')) {
                    $depth--;
                }
                if (0 === $depth) {
                    break;
                }
            }
            if (0 !== $depth || $open + 1 === $index) {
                throw new SchemaException('Empty or unbalanced SQLite CHECK expression');
            }

            $checks[] = [
                'name' => $name,
                'expression' => substr($sql, $tokens[$open]['end'], $tokens[$index]['start'] - $tokens[$open]['end']),
                'start' => null !== $name ? $tokens[$open - 3]['start'] : $token['start'],
                'end' => $tokens[$index]['end'],
            ];
        }

        return $checks;
    }

    /**
     * @param array<array{text: string, start: int, end: int, quoted: bool}> $tokens
     * @return array<string, string>
     */
    private function parseColumn(string $sql, array $tokens): array
    {
        if ([] === $tokens) {
            return [];
        }

        $name = $tokens[0]['text'];
        if (false === $tokens[0]['quoted'] &&
            in_array(strtoupper($name), ['CONSTRAINT', 'PRIMARY', 'UNIQUE', 'CHECK', 'FOREIGN'], true)) {
            return [];
        }

        $depth = 0;
        $expressionStart = null;
        foreach (array_slice($tokens, 1) as $index => $token) {
            if ($this->isToken($token, '(')) {
                if (0 === $depth && $this->isToken($tokens[$index], 'AS')) {
                    $expressionStart = $token['end'];
                }
                $depth++;
                continue;
            }

            if ($this->isToken($token, ')')) {
                $depth--;
                if (0 === $depth && null !== $expressionStart) {
                    $expression = substr($sql, $expressionStart, $token['start'] - $expressionStart);
                    if ('' === trim($expression)) {
                        throw new SchemaException(sprintf('Empty generation expression for SQLite column "%s"', $name));
                    }

                    return [$name => $expression];
                }
            }
        }

        return [];
    }

    /**
     * @param array{text: string, start: int, end: int, quoted: bool} $token
     */
    private function isToken(array $token, string $value): bool
    {
        return false === $token['quoted'] && 0 === strcasecmp($token['text'], $value);
    }

    /**
     * Token positions let expressions retain their original whitespace and comments.
     * Quoted tokens are opaque to the parenthesis and keyword scanner.
     *
     * @return array<array{text: string, start: int, end: int, quoted: bool}>
     */
    private function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $position = 0;
        while ($position < $length) {
            $character = $sql[$position];
            if (str_contains(" \t\r\n\v\f", $character)) {
                $position++;
                continue;
            }

            if ('--' === substr($sql, $position, 2)) {
                $position += strcspn($sql, "\r\n", $position);
                continue;
            }

            if ('/*' === substr($sql, $position, 2)) {
                $end = strpos($sql, '*/', $position + 2);
                if (false === $end) {
                    throw new SchemaException('Unterminated comment in SQLite CREATE TABLE');
                }
                $position = $end + 2;
                continue;
            }

            $start = $position;
            $quoted = in_array($character, ["'", '"', '`', '['], true);
            if ($quoted) {
                $closing = '[' === $character ? ']' : $character;
                $position++;
                while (true) {
                    $end = strpos($sql, $closing, $position);
                    if (false === $end) {
                        throw new SchemaException('Unterminated quoted token in SQLite CREATE TABLE');
                    }
                    $position = $end + 1;
                    if ('[' !== $character && $position < $length && $closing === $sql[$position]) {
                        $position++;
                        continue;
                    }
                    break;
                }
                $text = substr($sql, $start + 1, $position - $start - 2);
                if ('[' !== $character) {
                    $text = str_replace($closing . $closing, $closing, $text);
                }
            } elseif (1 === preg_match(
                '/(?:0[xX][0-9a-fA-F_]+|(?:[0-9][0-9_]*(?:\.[0-9_]*)?|\.[0-9][0-9_]*)' .
                '(?:[eE][+-]?[0-9][0-9_]*)?)/A',
                $sql,
                $matches,
                0,
                $position,
            )) {
                $text = $matches[0];
                $position += strlen($text);
            } elseif (1 === preg_match('/[a-zA-Z0-9_$\x80-\xff]+/A', $sql, $matches, 0, $position)) {
                $text = $matches[0];
                $position += strlen($text);
            } else {
                $text = $character;
                $position++;
            }

            $tokens[] = ['text' => $text, 'start' => $start, 'end' => $position, 'quoted' => $quoted];
        }

        return $tokens;
    }
}
