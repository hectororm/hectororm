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
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\StringType;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

class StringTypeTest extends TestCase
{
    public function testFromSchema(): void
    {
        $type = new StringType();

        $this->assertSame('1', $type->fromSchema('1'));
        $this->assertSame('1', $type->fromSchema(1));
        $this->assertSame('1.5', $type->fromSchema(1.5));
        $this->assertSame('1.5', $type->fromSchema('1.5'));
        $this->assertSame('1', $type->fromSchema(true));
        $this->assertSame('', $type->fromSchema(false));
    }

    public function testFromSchemaWithNotScalar(): void
    {
        $this->expectException(ValueError::class);

        $type = new StringType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin(): void
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new StringType();

        $this->assertSame('1', $type->fromSchema('1', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue(): void
    {
        $this->expectException(ValueError::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new StringType();

        $this->assertSame('1', $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin(): void
    {
        $this->expectException(ValueError::class);

        $expectedType = new ExpectedType('string', false, false);

        $type = new StringType();
        $type->fromSchema('1', $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeBackedEnum(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $expectedType = new ExpectedType(FakeEnumString::class, false, false);

        $type = new StringType();

        $this->assertSame(FakeEnumString::FOO, $type->fromSchema('foo', $expectedType));
        $this->assertSame(FakeEnumString::BAR, $type->fromSchema('bar', $expectedType));
    }

    public function testToSchema(): void
    {
        $type = new StringType();

        $this->assertSame('1', $type->toSchema('1'));
        $this->assertSame('1', $type->toSchema(1));
        $this->assertSame('1.5', $type->toSchema(1.5));
        $this->assertSame('1.5', $type->toSchema('1.5'));
        $this->assertSame('1', $type->toSchema(true));
        $this->assertSame('', $type->toSchema(false));
    }

    public function testToSchemaWithBackedEnum(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $type = new StringType();

        // Symmetric to fromSchema(): a backed enum is persisted as its backing value.
        $this->assertSame('foo', $type->toSchema(FakeEnumString::FOO));
        $this->assertSame('bar', $type->toSchema(FakeEnumString::BAR));
        $this->assertSame('0', $type->toSchema(FakeEnumInt::FOO));
        $this->assertSame('1', $type->toSchema(FakeEnumInt::BAR));
    }

    public function testToSchemaWithBackedEnumAndMaxLength(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $type = new StringType(maxlength: 2);

        $this->assertSame('fo', $type->toSchema(FakeEnumString::FOO));
    }

    public function testToSchemaWithNotScalar(): void
    {
        $this->expectException(ValueError::class);

        $type = new StringType();
        $type->toSchema(['foo']);
    }

    public function testToSchemaWithObjectString(): void
    {
        $object = new class {
            public function __toString()
            {
                return 'foo';
            }
        };

        $type = new StringType();

        $this->assertEquals('foo', $type->toSchema($object));
    }

    public function testToSchemaWithMaxLength(): void
    {
        $type = new StringType(maxlength: 6);

        $this->assertSame('foo ba', $type->toSchema('foo bar baz'));
        $this->assertSame('foo', $type->toSchema('foo'));
    }

    public function testFromSchemaNullWithoutExpected(): void
    {
        $type = new StringType();

        $this->assertNull($type->fromSchema(null));
    }

    public function testFromSchemaNullWithNullableExpected(): void
    {
        $type = new StringType();

        $this->assertNull($type->fromSchema(null, new ExpectedType('string', true, true)));
    }

    public function testFromSchemaNullWithNonNullableExpected(): void
    {
        $this->expectException(ValueException::class);

        $type = new StringType();
        $type->fromSchema(null, new ExpectedType('string', false, true));
    }

    public function testToSchemaNull(): void
    {
        $type = new StringType();

        $this->assertNull($type->toSchema(null));
    }
}
