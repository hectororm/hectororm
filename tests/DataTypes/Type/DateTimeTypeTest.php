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

use DateTime;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\DateTimeType;
use Hector\DataTypes\TypeException;
use PHPUnit\Framework\TestCase;
use stdClass;

class DateTimeTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new DateTimeType();

        $this->assertEquals(new DateTime('2020-06-14 14:00:00'), $type->fromSchema('2020-06-14 14:00:00'));
    }

    public function testFromSchemaWithBadFormat()
    {
        $this->expectException(TypeException::class);

        $type = new DateTimeType();
        $type->fromSchema('BAD @FORMAT');
    }

    public function testFromSchemaWithNotValid()
    {
        $this->expectException(TypeException::class);

        $type = new DateTimeType();
        $type->fromSchema(1);
    }

    public function testFromSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new DateTimeType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinString()
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->fromSchema('2020-06-14 14:00:00', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinInt()
    {
        $expectedType = new ExpectedType('int', false, true);

        $type = new DateTimeType();

        $this->assertSame(
            (new DateTime('2020-06-14 14:00:00'))->getTimestamp(),
            $type->fromSchema('2020-06-14 14:00:00', $expectedType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinDateTime()
    {
        $expectedType = new ExpectedType('\DateTimeImmutable', false, false);

        $type = new DateTimeType();

        $this->assertEquals(
            new DateTime('2020-06-14 14:00:00'),
            $type->fromSchema('2020-06-14 14:00:00', $expectedType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new DateTimeType();
        $type->fromSchema(new stdClass(), $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $expectedType = new ExpectedType('\stdClass', false, false);

        $type = new DateTimeType();
        $type->fromSchema('2020-06-14 14:00:00', $expectedType);
    }

    public function testToSchema()
    {
        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200.));
    }

    public function testToSchemaWithBadFormat()
    {
        $this->expectException(TypeException::class);

        $type = new DateTimeType('BAD FORMAT');
        $type->toSchema('Y-m-d H:i:s');
    }

    public function testToSchemaDateTargetFormat()
    {
        $type = new DateTimeType('Y-m-d');

        $this->assertSame('2020-06-14', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14', $type->toSchema(1592143200));
        $this->assertSame('2020-06-14', $type->toSchema(1592143200.));
    }

    public function testToSchemaWithNotScalar()
    {
        $this->expectException(TypeException::class);

        $type = new DateTimeType();
        $type->toSchema(['foo']);
    }
}
