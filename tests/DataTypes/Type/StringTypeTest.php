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

use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\StringType;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

class StringTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new StringType();

        $this->assertSame('1', $type->fromSchema('1'));
        $this->assertSame('1', $type->fromSchema(1));
        $this->assertSame('1.5', $type->fromSchema(1.5));
        $this->assertSame('1.5', $type->fromSchema('1.5'));
        $this->assertSame('1', $type->fromSchema(true));
        $this->assertSame('', $type->fromSchema(false));
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new StringType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin()
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new StringType();

        $this->assertSame('1', $type->fromSchema('1', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(ValueError::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new StringType();

        $this->assertSame('1', $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(ValueError::class);

        $expectedType = new ExpectedType('string', false, false);

        $type = new StringType();
        $type->fromSchema('1', $expectedType);
    }

    public function testToSchema()
    {
        $type = new StringType();

        $this->assertSame('1', $type->toSchema('1'));
        $this->assertSame('1', $type->toSchema(1));
        $this->assertSame('1.5', $type->toSchema(1.5));
        $this->assertSame('1.5', $type->toSchema('1.5'));
        $this->assertSame('1', $type->toSchema(true));
        $this->assertSame('', $type->toSchema(false));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new StringType();
        $type->toSchema(['foo']);
    }

    public function testToSchemaWithObjectString()
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

    public function testToSchemaWithMaxLength()
    {
        $type = new StringType(maxlength: 6);

        $this->assertSame('foo ba', $type->toSchema('foo bar baz'));
        $this->assertSame('foo', $type->toSchema('foo'));
    }
}
