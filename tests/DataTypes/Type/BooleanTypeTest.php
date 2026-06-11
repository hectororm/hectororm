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

use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\BooleanType;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

class BooleanTypeTest extends TestCase
{
    public function testFromSchema(): void
    {
        $type = new BooleanType();

        $this->assertSame(true, $type->fromSchema(1));
        $this->assertSame(true, $type->fromSchema('1'));
        $this->assertSame(true, $type->fromSchema(true));
        $this->assertSame(true, $type->fromSchema('true'));
        $this->assertSame(false, $type->fromSchema(false));
        $this->assertSame(false, $type->fromSchema('false'));
        $this->assertSame(false, $type->fromSchema('0'));
        $this->assertSame(false, $type->fromSchema(0));
    }

    public function testFromSchemaWithNotScalar(): void
    {
        $this->expectException(ValueError::class);

        $type = new BooleanType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin(): void
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new BooleanType();

        $this->assertSame('1', $type->fromSchema('1', $expectedType));
    }

    public function testFromSchemaTextualFalseWithBoolExpected(): void
    {
        $expectedType = new ExpectedType('bool', false, true);

        $type = new BooleanType();

        // The textual MySQL string "false" must not be cast to true by settype().
        $this->assertSame(false, $type->fromSchema('false', $expectedType));
        $this->assertSame(false, $type->fromSchema('FALSE', $expectedType));
        $this->assertSame(true, $type->fromSchema('true', $expectedType));
        $this->assertSame(true, $type->fromSchema('TRUE', $expectedType));
    }

    public function testFromSchemaTextualFalseWithIntExpected(): void
    {
        $expectedType = new ExpectedType('int', false, true);

        $type = new BooleanType();

        $this->assertSame(0, $type->fromSchema('false', $expectedType));
        $this->assertSame(1, $type->fromSchema('true', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue(): void
    {
        $this->expectException(ValueError::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new BooleanType();

        $this->assertSame('1', $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin(): void
    {
        $this->expectException(ValueError::class);

        $expectedType = new ExpectedType('string', false, false);

        $type = new BooleanType();
        $type->fromSchema('1', $expectedType);
    }

    public function testToSchema(): void
    {
        $type = new BooleanType();

        $this->assertSame(1, $type->toSchema(1));
        $this->assertSame(1, $type->toSchema('1'));
        $this->assertSame(1, $type->toSchema(true));
        $this->assertSame(1, $type->toSchema('true'));
        $this->assertSame(0, $type->toSchema(false));
        $this->assertSame(0, $type->toSchema('false'));
        $this->assertSame(0, $type->toSchema('0'));
        $this->assertSame(0, $type->toSchema(0));
    }

    public function testToSchemaWithNotScalar(): void
    {
        $this->expectException(ValueError::class);

        $type = new BooleanType();
        $type->toSchema(['foo']);
    }

    public function testFromSchemaNullWithoutExpected(): void
    {
        $type = new BooleanType();

        $this->assertNull($type->fromSchema(null));
    }

    public function testFromSchemaNullWithNullableExpected(): void
    {
        $type = new BooleanType();

        $this->assertNull($type->fromSchema(null, new ExpectedType('bool', true, true)));
    }

    public function testFromSchemaNullWithNonNullableExpected(): void
    {
        $this->expectException(ValueException::class);

        $type = new BooleanType();
        $type->fromSchema(null, new ExpectedType('bool', false, true));
    }

    public function testToSchemaNull(): void
    {
        $type = new BooleanType();

        $this->assertNull($type->toSchema(null));
    }
}
