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

/**
 * @internal Helper class
 */
class Helper
{
    /**
     * Trim name.
     *
     * @param string|null $name
     *
     * @return string|null
     */
    public static function trim(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        return trim($name, " \t\n\r\0\x0B`") ?: null;
    }

    /**
     * Quote.
     *
     * @param string|null $name
     *
     * @return string|null
     */
    public static function quote(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        return sprintf('`%s`', $name);
    }
}
