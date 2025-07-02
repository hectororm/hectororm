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
use Hector\DataTypes\Type\NumericType;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

class NumericTypeTest extends TestCase
{
    public function testFromSchemaFloat()
    {
        $type = new NumericType('float');

        $this->assertSame(1., $type->fromSchema('1'));
        $this->assertSame(1., $type->fromSchema(1));
        $this->assertSame(1.5, $type->fromSchema(1.5));
        $this->assertSame(1.5, $type->fromSchema('1.5'));
        $this->assertSame(1., $type->fromSchema(true));
        $this->assertSame(0., $type->fromSchema(false));
    }

    public function testFromSchemaInteger()
    {
        $type = new NumericType('int');

        $this->assertSame(1, $type->fromSchema('1'));
        $this->assertSame(1, $type->fromSchema(1));
        $this->assertSame(1, $type->fromSchema(1.5));
        $this->assertSame(1, $type->fromSchema('1.5'));
        $this->assertSame(1, $type->fromSchema(true));
        $this->assertSame(0, $type->fromSchema(false));
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new NumericType('int');

        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltin()
    {
        $expectedType = new ExpectedType('float', false, true);

        $type = new NumericType('float');

        $this->assertSame(1., $type->fromSchema('1', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(ValueError::class);
        $expectedType = new ExpectedType('float', false, true);

        $type = new NumericType('float');

        $this->assertSame(1., $type->fromSchema(new stdClass(), $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(ValueError::class);

        $expectedType = new ExpectedType('float', false, false);

        $type = new NumericType('float');
        $type->fromSchema('1', $expectedType);
    }

    public function testToSchemaFloat()
    {
        $type = new NumericType('float');

        $this->assertSame(1., $type->toSchema('1'));
        $this->assertSame(1., $type->toSchema(1));
        $this->assertSame(1.5, $type->toSchema(1.5));
        $this->assertSame(1.5, $type->toSchema('1.5'));
        $this->assertSame(1., $type->toSchema(true));
        $this->assertSame(0., $type->toSchema(false));
    }

    public function testToSchemaInteger()
    {
        $type = new NumericType('int');

        $this->assertSame(1, $type->toSchema('1'));
        $this->assertSame(1, $type->toSchema(1));
        $this->assertSame(1, $type->toSchema(1.5));
        $this->assertSame(1, $type->toSchema('1.5'));
        $this->assertSame(1, $type->toSchema(true));
        $this->assertSame(0, $type->toSchema(false));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(ValueError::class);

        $type = new NumericType('float');
        $type->toSchema(['foo']);
    }
}
