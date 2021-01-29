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
 *
 * @package Hector\Schema
 */
trait NameHelperTrait
{
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
        return sprintf('`%s`', $name);
    }

    /**
     * Quote names.
     *
     * @param string[] $names
     * @param string|null $alias
     *
     * @return string[]
     */
    protected function addAliasToNames(array $names, ?string $alias): array
    {
        return array_values(array_map(fn(string $name) => $this->addAliasToName($name, $alias), $names));
    }

    /**
     * Add alias to name.
     *
     * @param string $name
     * @param string|null $alias
     *
     * @return string
     */
    protected function addAliasToName(string $name, ?string $alias): string
    {
        if (null === $alias) {
            return $name;
        }

        return sprintf('%s.%s', $alias, $name);
    }
}