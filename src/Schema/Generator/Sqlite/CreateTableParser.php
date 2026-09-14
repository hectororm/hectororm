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
 * Extract generated expressions and rewrite their column-reference tokens.
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
                        'Cannot safely rewrite unquoted keyword "%s" in a generated expression; ' .
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
        $tokens = $this->tokenize($sql);
        $depth = 0;
        $start = null;
        $expressions = [];

        foreach ($tokens as $index => $token) {
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
                    $expressions += $this->parseColumn($sql, array_slice($tokens, $start, $index - $start));

                    return $expressions;
                }
                if (0 > $depth) {
                    break;
                }
                continue;
            }

            if (1 === $depth && $this->isToken($token, ',') && null !== $start) {
                $expressions += $this->parseColumn($sql, array_slice($tokens, $start, $index - $start));
                $start = $index + 1;
            }
        }

        throw new SchemaException('Cannot parse SQLite CREATE TABLE column definitions');
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
