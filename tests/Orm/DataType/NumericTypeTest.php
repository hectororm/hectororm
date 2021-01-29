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

use Hector\Orm\DataType\BigIntType;
use Hector\Orm\DataType\DecimalType;
use Hector\Orm\DataType\DoubleType;
use Hector\Orm\DataType\FloatType;
use Hector\Orm\DataType\IntType;
use Hector\Orm\DataType\MediumIntType;
use Hector\Orm\DataType\NumericType;
use Hector\Orm\DataType\SmallIntType;
use Hector\Orm\DataType\TinyIntType;
use Hector\Orm\DataType\TypeInterface;
use Hector\Orm\Exception\TypeException;
use PHPUnit\Framework\TestCase;
use stdClass;

class NumericTypeTest extends TestCase
{
    public function providerFloat()
    {
        return [
            [
                'type' => new FloatType(),
                'name' => 'float'
            ],
            [
                'type' => new DecimalType(),
                'name' => 'decimal'
            ],
            [
                'type' => new DoubleType(),
                'name' => 'double'
            ],
            [
                'type' => new NumericType(),
                'name' => 'numeric'
            ],
        ];
    }

    public function providerInteger()
    {
        return [
            [
                'type' => new BigIntType(),
                'name' => 'bigint'
            ],
            [
                'type' => new IntType(),
                'name' => 'int'
            ],
            [
                'type' => new MediumIntType(),
                'name' => 'mediumint'
            ],
            [
                'type' => new SmallIntType(),
                'name' => 'smallint'
            ],
            [
                'type' => new TinyIntType(),
                'name' => 'tinyint'
            ],
        ];
    }

    /**
     * @dataProvider providerFloat
     */
    public function testFromSchemaFloat(TypeInterface $type, string $name)
    {
        $this->assertEquals($name, $type->getName());
        $this->assertSame(1., $type->fromSchema('1'));
        $this->assertSame(1., $type->fromSchema(1));
        $this->assertSame(1.5, $type->fromSchema(1.5));
        $this->assertSame(1.5, $type->fromSchema('1.5'));
        $this->assertSame(1., $type->fromSchema(true));
        $this->assertSame(0., $type->fromSchema(false));
    }

    /**
     * @dataProvider providerInteger
     */
    public function testFromSchemaInteger(TypeInterface $type, string $name)
    {
        $this->assertEquals($name, $type->getName());
        $this->assertSame(1, $type->fromSchema('1'));
        $this->assertSame(1, $type->fromSchema(1));
        $this->assertSame(1, $type->fromSchema(1.5));
        $this->assertSame(1, $type->fromSchema('1.5'));
        $this->assertSame(1, $type->fromSchema(true));
        $this->assertSame(0, $type->fromSchema(false));
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new FloatType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin()
    {
        $declaredType = new FakeReflectionNamedType('float', false, true);

        $type = new FloatType();

        $this->assertSame(1., $type->fromSchema('1', $declaredType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $declaredType = new FakeReflectionNamedType('float', false, true);

        $type = new FloatType();

        $this->assertSame(1., $type->fromSchema(new stdClass(), $declaredType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $declaredType = new FakeReflectionNamedType('float', false, false);

        $type = new FloatType();
        $type->fromSchema('1', $declaredType);
    }

    /**
     * @dataProvider providerFloat
     */
    public function testToSchemaFloat(TypeInterface $type, string $name)
    {
        $this->assertEquals($type->getName(), $name);
        $this->assertSame(1., $type->toSchema('1'));
        $this->assertSame(1., $type->toSchema(1));
        $this->assertSame(1.5, $type->toSchema(1.5));
        $this->assertSame(1.5, $type->toSchema('1.5'));
        $this->assertSame(1., $type->toSchema(true));
        $this->assertSame(0., $type->toSchema(false));
    }

    /**
     * @dataProvider providerInteger
     */
    public function testToSchemaInteger(TypeInterface $type, string $name)
    {
        $this->assertEquals($type->getName(), $name);
        $this->assertSame(1, $type->toSchema('1'));
        $this->assertSame(1, $type->toSchema(1));
        $this->assertSame(1, $type->toSchema(1.5));
        $this->assertSame(1, $type->toSchema('1.5'));
        $this->assertSame(1, $type->toSchema(true));
        $this->assertSame(0, $type->toSchema(false));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new FloatType();
        $type->toSchema(['foo']);
    }
}
