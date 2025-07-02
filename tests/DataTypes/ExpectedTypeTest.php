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

namespace Hector\DataTypes\Tests;

use Hector\DataTypes\ExpectedType;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use stdClass;

class ExpectedTypeTest extends TestCase
{
    public function fromDataProvider(): array
    {
        return [
            [
                ['type' => new FakeReflectionNamedType('string', true, true), 'allowsNull' => false],
                ['name' => 'string', 'allowsNull' => true, 'builtin' => true]
            ],
            [
                ['type' => new FakeReflectionNamedType('string', false, true), 'allowsNull' => true],
                ['name' => 'string', 'allowsNull' => false, 'builtin' => true]
            ],
            [
                ['type' => 'string', 'allowsNull' => false],
                ['name' => 'string', 'allowsNull' => false, 'builtin' => true]
            ],
            [
                ['type' => 'string', 'allowsNull' => true],
                ['name' => 'string', 'allowsNull' => true, 'builtin' => true]
            ],
        ];
    }

    /**
     * @dataProvider fromDataProvider
     */
    public function testFrom($type, $expected)
    {
        $expectedType = ExpectedType::from(...$type);

        $this->assertSame($expected['name'], $expectedType->getName());
        $this->assertSame($expected['allowsNull'], $expectedType->allowsNull());
        $this->assertSame($expected['builtin'], $expectedType->isBuiltin());
    }

    public function fromStringDataProvider(): array
    {
        return [
            [
                ['name' => 'bool', 'allowsNull' => true],
                ['name' => 'bool', 'builtin' => true]
            ],
            [
                ['name' => 'boolean', 'allowsNull' => true],
                ['name' => 'bool', 'builtin' => true]
            ],
            [
                ['name' => 'int', 'allowsNull' => true],
                ['name' => 'int', 'builtin' => true]
            ],
            [
                ['name' => 'integer', 'allowsNull' => true],
                ['name' => 'int', 'builtin' => true]
            ],
            [
                ['name' => 'float', 'allowsNull' => true],
                ['name' => 'float', 'builtin' => true]
            ],
            [
                ['name' => 'string', 'allowsNull' => true],
                ['name' => 'string', 'builtin' => true]
            ],
            [
                ['name' => 'array', 'allowsNull' => true],
                ['name' => 'array', 'builtin' => true]
            ],
            [
                ['name' => 'bool', 'allowsNull' => false],
                ['name' => 'bool', 'builtin' => true]
            ],
            [
                ['name' => 'boolean', 'allowsNull' => false],
                ['name' => 'bool', 'builtin' => true]
            ],
            [
                ['name' => 'int', 'allowsNull' => false],
                ['name' => 'int', 'builtin' => true]
            ],
            [
                ['name' => 'integer', 'allowsNull' => false],
                ['name' => 'int', 'builtin' => true]
            ],
            [
                ['name' => 'float', 'allowsNull' => false],
                ['name' => 'float', 'builtin' => true]
            ],
            [
                ['name' => 'string', 'allowsNull' => false],
                ['name' => 'string', 'builtin' => true]
            ],
            [
                ['name' => 'array', 'allowsNull' => false],
                ['name' => 'array', 'builtin' => true]
            ],
            [
                ['name' => stdClass::class, 'allowsNull' => false],
                ['name' => stdClass::class, 'builtin' => false]
            ],
        ];
    }

    /**
     * @dataProvider fromStringDataProvider
     */
    public function testFromString(array $type, array $expected)
    {
        $expectedType = ExpectedType::fromString(...$type);

        $this->assertSame($expected['name'], $expectedType->getName());
        $this->assertSame($type['allowsNull'], $expectedType->allowsNull());
        $this->assertSame($expected['builtin'], $expectedType->isBuiltin());
    }

    public function fromReflectionDataProvider(): array
    {
        return [
            [
                new FakeReflectionNamedType('string', true, true),
                new FakeReflectionNamedType('string', false, true),
                new FakeReflectionNamedType('int', true, true),
                new FakeReflectionNamedType('int', false, true),
                new FakeReflectionNamedType(stdClass::class, true, false),
                new FakeReflectionNamedType(stdClass::class, false, false),
            ]
        ];
    }

    /**
     * @dataProvider fromReflectionDataProvider
     */
    public function testFromReflection(ReflectionNamedType $reflection)
    {
        $expected = ExpectedType::from($reflection);

        $this->assertSame($reflection->getName(), $expected->getName());
        $this->assertSame($reflection->allowsNull(), $expected->allowsNull());
        $this->assertSame($reflection->isBuiltin(), $expected->isBuiltin());
    }
}
