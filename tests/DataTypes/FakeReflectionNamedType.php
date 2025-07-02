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

namespace Hector\DataTypes\Tests;

use ReflectionNamedType;

class FakeReflectionNamedType extends ReflectionNamedType
{
    private string $fake_name;
    private bool $fake_allowsNull ;
    private bool $fake_builtin ;

    public function __construct(
        string $name,
        bool $allowsNull = false,
        bool $builtin = false
    ) {
        $this->fake_name = $name;
        $this->fake_allowsNull = $allowsNull;
        $this->fake_builtin = $builtin;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->fake_name;
    }

    /**
     * @inheritDoc
     */
    public function allowsNull(): bool
    {
        return $this->fake_allowsNull;
    }

    /**
     * @inheritDoc
     */
    public function isBuiltin(): bool
    {
        return $this->fake_builtin;
    }
}