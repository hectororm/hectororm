<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema;

/**
 * Trait NameHelperTrait.
 */
trait NameHelperTrait
{
    /**
     * Trim name.
     *
     * @param string|null $name
     *
     * @return string|null
     */
    protected function trimName(?string $name): ?string
    {
        if (null === $name) {
            return null;
        }

        return trim($name, " \t\n\r\0\x0B`") ?: null;
    }

    /**
     * Quote names.
     *
     * @param string[] $names
     *
     * @return string[]
     */
    protected function quoteNames(array $names): array
    {
        return array_values(array_map(fn(string $name) => $this->quoteName($name), $names));
    }

    /**
     * Quote name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function quoteName(string $name): string
    {
        return sprintf('`%s`', $this->trimName($name));
    }

    /**
     * Quote names.
     *
     * @param string[] $names
     * @param string|null $alias
     * @param bool $quote
     *
     * @return string[]
     */
    protected function addAliasToNames(array $names, ?string $alias, bool $quote = false): array
    {
        return array_values(array_map(fn(string $name) => $this->addAliasToName($name, $alias, $quote), $names));
    }

    /**
     * Add alias to name.
     *
     * @param string $name
     * @param string|null $alias
     * @param bool $quoted
     *
     * @return string
     */
    protected function addAliasToName(string $name, ?string $alias, bool $quoted = false): string
    {
        if (null === $alias) {
            return match ($quoted) {
                true => $this->quoteName($name),
                false => $this->trimName($name),
            };
        }

        return match ($quoted) {
            true => sprintf('%s.%s', $this->quoteName($alias), $this->quoteName($name)),
            false => sprintf('%s.%s', $this->trimName($alias), $this->trimName($name)),
        };
    }
}
