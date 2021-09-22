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
use Hector\DataTypes\Type\BooleanType;
use Hector\DataTypes\TypeException;
use PHPUnit\Framework\TestCase;
use stdClass;

class BooleanTypeTest extends TestCase
{
    public function testFromSchema()
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

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new BooleanType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin()
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new BooleanType();

        $this->assertSame('1', $type->fromSchema('1', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new BooleanType();

        $this->assertSame('1', $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $expectedType = new ExpectedType('string', false, false);

        $type = new BooleanType();
        $type->fromSchema('1', $expectedType);
    }

    public function testToSchema()
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

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new BooleanType();
        $type->toSchema(['foo']);
    }
}
