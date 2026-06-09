<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2023 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query;

use Hector\Query\Statement\Quoted;
use Stringable;

/**
 * @internal Helper class
 */
class Helper
{
    /**
     * Trim name.
     *
     * The second parameter is the set of characters to strip (like the native
     * {@see trim()}), defaulting to whitespace. Identifier de-quoting is handled
     * by {@see Helper::unquote()}.
     *
     * @param string|null $name
     * @param string $characters
     *
     * @return string|null
     */
    public static function trim(?string $name, string $characters = " \t\n\r\0\x0B"): ?string
    {
        if (null === $name) {
            return null;
        }

        return trim($name, $characters) ?: null;
    }

    /**
     * Unquote an identifier.
     *
     * Trims surrounding whitespace then strips a single enclosing quote pair when the
     * first and last characters are the same quote character listed in $quotes, undoubling
     * that character inside (strict inverse of {@see Helper::quote()} for its outputs).
     * Whitespace enclosed within the quote pair is preserved. A value that is not enclosed
     * in a matching pair is returned trimmed but otherwise unchanged.
     *
     * @param string|null $name
     * @param string $quotes Identifier quote characters (default backtick and double quote)
     *
     * @return string|null
     */
    public static function unquote(?string $name, string $quotes = '`"'): ?string
    {
        $name = self::trim($name);

        if (null === $name) {
            return null;
        }

        if (strlen($name) < 2) {
            return $name;
        }

        $first = $name[0];

        if (false === str_contains($quotes, $first) || $name[strlen($name) - 1] !== $first) {
            return $name;
        }

        $inner = substr($name, 1, -1);

        return str_replace($first . $first, $first, $inner);
    }

    /**
     * Quote.
     *
     * @param string|null $name
     * @param string $quote
     *
     * @return string|null
     */
    public static function quote(?string $name, string $quote = '`'): ?string
    {
        if (null === $name) {
            return null;
        }

        return sprintf('%1$s%2$s%1$s', $quote, str_replace($quote, $quote . $quote, $name));
    }

    /**
     * Escape LIKE wildcard characters.
     *
     * @param string $value
     *
     * @return string
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Split a (possibly qualified, possibly quoted) SQL identifier path on its dot
     * separators, ignoring dots enclosed in a matching pair of identifier quotes
     * (backtick or double quote).
     *
     * Behaves like {@see explode()} with a positive limit: the last segment holds
     * the unsplit remainder of the path ($limit is clamped to at least 1). Quotes are
     * kept on the segments (raw, not trimmed); it is up to the caller to trim them when
     * needed. This method only splits and does not validate the path nor interpret
     * wildcards.
     *
     * $quotes lists the identifier quote characters; a dot enclosed in a matching pair
     * of one of these characters is not treated as a separator. Pass an empty string to
     * disable quote awareness and split unconditionally on every dot.
     *
     * An unterminated quote keeps the rest of the path (dots included) in the current
     * segment, since the split cannot know where the quoted identifier was meant to end.
     * This method is only fed validated/trusted identifiers, so malformed input is not
     * specially handled.
     *
     * Examples:
     *  - "schema.table.column"  => ["schema", "table", "column"]
     *  - "`a.b`.`c`"            => ["`a.b`", "`c`"]
     *  - "a.b.c", limit 2       => ["a", "b.c"]
     *
     * @param string|Stringable $path
     * @param int $limit
     * @param string $quotes Identifier quote characters (default backtick and double quote)
     *
     * @return string[]
     */
    public static function explodePath(string|Stringable $path, int $limit = PHP_INT_MAX, string $quotes = '`"'): array
    {
        $path = (string)$path;

        if ($limit < 2) {
            return [$path];
        }

        $segments = [];
        $current = '';
        $quote = null;
        $count = 0;
        $length = strlen($path);

        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];

            if (null !== $quote) {
                $current .= $char;

                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if (str_contains($quotes, $char)) {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ('.' === $char && $count < $limit - 1) {
                $segments[] = $current;
                $current = '';
                $count++;
                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * Whether the value is a (possibly qualified, possibly quoted) column reference,
     * as opposed to an SQL expression/function, closure or sub-query.
     *
     * A Quoted statement is, by construction, an identifier reference and always
     * returns true. A string is accepted only if every dot-separated segment is
     * either:
     *  - a quoted identifier (backtick or double quote, with the quote character
     *    doubled to escape it), which may contain anything including dots or spaces; or
     *  - a bare identifier starting with a Unicode letter or underscore (so numeric
     *    literals such as `123` or `1.2` are rejected).
     *
     * Expressions/functions (e.g. RAND(), COUNT(*)), arithmetic and any other type
     * return false.
     *
     * @param mixed $column
     *
     * @return bool
     */
    public static function isColumnReference(mixed $column): bool
    {
        if ($column instanceof Quoted) {
            return true;
        }

        if (false === is_string($column)) {
            return false;
        }

        $segment = '(?:`(?:[^`]|``)*`|"(?:[^"]|"")*"|[\p{L}_][\p{L}\p{N}_]*)';

        return 1 === preg_match('/^' . $segment . '(?:\.' . $segment . ')*$/u', $column);
    }
}
