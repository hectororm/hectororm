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

use Hector\Orm\DataType\VarCharType;
use Hector\Orm\Exception\TypeException;
use Hector\Schema\Column;
use PHPUnit\Framework\TestCase;

class StringTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new VarCharType();

        $this->assertSame('1', $type->fromSchema('1'));
        $this->assertSame('1', $type->fromSchema(1));
        $this->assertSame('1.5', $type->fromSchema(1.5));
        $this->assertSame('1.5', $type->fromSchema('1.5'));
        $this->assertSame('1', $type->fromSchema(true));
        $this->assertSame('', $type->fromSchema(false));
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new VarCharType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin()
    {
        $declaredType = new FakeReflectionNamedType('string', false, true);

        $type = new VarCharType();

        $this->assertSame('1', $type->fromSchema('1', $declaredType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $declaredType = new FakeReflectionNamedType('string', false, true);

        $type = new VarCharType();

        $this->assertSame('1', $type->fromSchema(new \stdClass(), $declaredType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $declaredType = new FakeReflectionNamedType('string', false, false);

        $type = new VarCharType();
        $type->fromSchema('1', $declaredType);
    }

    public function testToSchema()
    {
        $type = new VarCharType();

        $this->assertSame('1', $type->toSchema('1'));
        $this->assertSame('1', $type->toSchema(1));
        $this->assertSame('1.5', $type->toSchema(1.5));
        $this->assertSame('1.5', $type->toSchema('1.5'));
        $this->assertSame('1', $type->toSchema(true));
        $this->assertSame('', $type->toSchema(false));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new VarCharType();
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

        $type = new VarCharType();

        $this->assertEquals('foo', $type->toSchema($object));
    }
}
