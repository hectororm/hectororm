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

/**
 * @internal Helper class
 */
class Helper
{
    /**
     * Trim name.
     *
     * @param string|null $name
     * @param string $quote
     *
     * @return string|null
     */
    public static function trim(?string $name, string $quote = '`'): ?string
    {
        if (null === $name) {
            return null;
        }

        return trim($name, " \t\n\r\0\x0B" . $quote) ?: null;
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
     * Whether the value is a (possibly qualified, possibly quoted) column reference,
     * as opposed to an SQL expression/function, closure or sub-query.
     *
     * A Quoted statement is, by construction, an identifier reference and always
     * returns true. A string is accepted only if it matches a plain (optionally
     * dotted) identifier; expressions/functions (e.g. RAND(), COUNT(*)) and any
     * other type return false.
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

        return 1 === preg_match('/^(?:(["`])\w+\1|\w+)(?:\.(?:(["`])\w+\2|\w+))*$/', $column);
    }
}
