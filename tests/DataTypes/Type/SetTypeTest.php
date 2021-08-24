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
use Hector\DataTypes\Type\SetType;
use Hector\DataTypes\TypeException;
use PHPUnit\Framework\TestCase;

class SetTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new SetType();

        $this->assertEquals(['foo', 'bar'], $type->fromSchema('foo, bar'));
    }

    public function testFromSchemaWithNotValid()
    {
        $this->expectException(TypeException::class);

        $type = new SetType();
        $type->fromSchema(1);
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new SetType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinArray()
    {
        $expectedType = new ExpectedType('array', false, true);
        $type = new SetType();

        $this->assertEquals(['foo', 'bar'], $type->fromSchema('foo, bar', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinString()
    {
        $expectedType = new ExpectedType('string', false, true);
        $type = new SetType();

        $this->assertEquals('foo,bar', $type->fromSchema('foo, bar', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinInvalid()
    {
        $this->expectException(TypeException::class);

        $expectedType = new ExpectedType('int', false, true);
        $type = new SetType();

        $type->fromSchema('foo, bar', $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new SetType();
        $type->fromSchema(new \stdClass(), $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $expectedType = new ExpectedType('\stdClass', false, false);

        $type = new SetType();
        $type->fromSchema('foo, bar', $expectedType);
    }

    public function testToSchema()
    {
        $type = new SetType();

        $this->assertSame('foo,bar', $type->toSchema(['foo', 'bar']));
    }

    public function testToSchemaWithBadType()
    {
        $this->expectException(TypeException::class);

        $type = new SetType();
        $fakeObject = new class {
        };

        $type->toSchema($fakeObject);
    }
}
