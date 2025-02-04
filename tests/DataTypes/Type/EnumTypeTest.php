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

namespace Hector\DataTypes\Tests\Type;

use BackedEnum;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\EnumType;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

class EnumTypeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }
    }

    public function testFromSchema_int()
    {
        $type = new EnumType(FakeEnumInt::class);

        $this->assertSame(FakeEnumInt::FOO, $type->fromSchema(0));
        $this->assertSame(FakeEnumInt::BAR, $type->fromSchema(1));
    }

    public function testFromSchema_string()
    {
        $type = new EnumType(FakeEnumString::class);

        $this->assertSame(FakeEnumString::FOO, $type->fromSchema('foo'));
        $this->assertSame(FakeEnumString::BAR, $type->fromSchema('bar'));
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new EnumType(FakeEnumString::class);
        $type->fromSchema(['foo']);
    }

    public function testFromSchema_try()
    {
        $type = new EnumType(FakeEnumString::class, true);

        $this->assertSame(FakeEnumString::FOO, $type->fromSchema('foo'));
        $this->assertNull($type->fromSchema('qux'));
    }

    public function testFromSchema_noTry()
    {
        $this->expectException(ValueError::class);
        $type = new EnumType(FakeEnumString::class, false);
        $type->fromSchema('qux');
    }

    public function testFromSchemaWithDeclaredTypeStringBuiltin()
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new EnumType(FakeEnumString::class);

        $this->assertSame('foo', $type->fromSchema('foo', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(ValueError::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new EnumType(FakeEnumString::class);

        $this->assertSame('1', $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(ValueError::class);

        $expectedType = new ExpectedType('string', false, false);

        $type = new EnumType(FakeEnumString::class);
        $type->fromSchema('1', $expectedType);
    }

    public function testToSchema()
    {
        $type = new EnumType(FakeEnumString::class);

        $this->assertSame('foo', $type->toSchema(FakeEnumString::FOO));
        $this->assertSame('bar', $type->toSchema(FakeEnumString::BAR));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new EnumType(FakeEnumString::class);
        $type->toSchema(['foo']);
    }
}
