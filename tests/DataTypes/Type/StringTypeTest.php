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

    public function testFromSchemaWithIntBackedEnum(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $expectedType = new ExpectedType(FakeEnumInt::class, false, false);

        $type = new StringType();

        // PDO returns every column as a string, so an int-backed enum receives a
        // numeric string; it must still be hydrated (no raw TypeError).
        $this->assertSame(FakeEnumInt::FOO, $type->fromSchema('0', $expectedType));
        $this->assertSame(FakeEnumInt::BAR, $type->fromSchema('1', $expectedType));
        $this->assertSame(FakeEnumInt::FOO, $type->fromSchema(0, $expectedType));
    }

    public function testFromSchemaWithUnknownEnumValueWrapsException(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $this->expectException(ValueException::class);

        $type = new StringType();
        $type->fromSchema('does-not-exist', new ExpectedType(FakeEnumString::class, false, false));
    }

    public function testFromSchemaWithUnknownIntBackedEnumValueWrapsException(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $this->expectException(ValueException::class);

        $type = new StringType();
        $type->fromSchema('999', new ExpectedType(FakeEnumInt::class, false, false));
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

    /**
     * Exercises AbstractType::equals() (inherited, no override in StringType):
     * real changes hidden by the previous loose `==` must now be detected.
     */
    public function testEqualsDetectsRealChanges(): void
    {
        $type = new StringType();

        $this->assertFalse($type->equals('1e3', '1000'));
        $this->assertFalse($type->equals('1.0', '1'));
        $this->assertFalse($type->equals('10', '1e1'));
        $this->assertFalse($type->equals('0', false));
        $this->assertFalse($type->equals(null, ''));
        $this->assertFalse($type->equals(null, 0));
    }

    /**
     * The legitimate int/float vs numeric-string juggling between the entity and
     * the database value must still compare as equal (no spurious UPDATE).
     */
    public function testEqualsKeepsTypeJugglingEqual(): void
    {
        $type = new StringType();

        $this->assertTrue($type->equals(1, '1'));
        $this->assertTrue($type->equals(1.5, '1.5'));
        $this->assertTrue($type->equals('1', '1'));
        $this->assertTrue($type->equals(1, 1));
        $this->assertTrue($type->equals(null, null));
    }
}
