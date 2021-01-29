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

namespace Hector\Orm\Tests\DataType;

use ReflectionNamedType;

class FakeReflectionNamedType extends ReflectionNamedType
{
    private string $fakeName;
    private bool $fakeAllowsNull;
    private bool $fakeIsBuiltin;

    public function __construct(string $name, bool $allowsNull = false, bool $isBuiltin = false)
    {
        $this->fakeName = $name;
        $this->fakeAllowsNull = $allowsNull;
        $this->fakeIsBuiltin = $isBuiltin;
    }

    public function getName()
    {
        return $this->fakeName;
    }

    public function allowsNull()
    {
        return $this->fakeAllowsNull;
    }

    public function isBuiltin()
    {
        return $this->fakeIsBuiltin;
    }
}