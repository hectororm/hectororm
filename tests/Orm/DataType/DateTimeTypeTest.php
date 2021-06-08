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

use DateTime;
use Hector\Orm\DataType\DateTime\DateTimeType;
use Hector\Orm\DataType\DateTime\DateType;
use Hector\Orm\Exception\TypeException;
use PHPUnit\Framework\TestCase;
use stdClass;

class DateTimeTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new DateTimeType();

        $this->assertEquals(new DateTime('2020-06-14 14:00:00'), $type->fromSchema('2020-06-14 14:00:00'));
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
        $declaredType = new FakeReflectionNamedType('string', false, true);

        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->fromSchema('2020-06-14 14:00:00', $declaredType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinInt()
    {
        $declaredType = new FakeReflectionNamedType('int', false, true);

        $type = new DateTimeType();

        $this->assertSame(
            (new DateTime('2020-06-14 14:00:00'))->getTimestamp(),
            $type->fromSchema('2020-06-14 14:00:00', $declaredType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinDateTime()
    {
        $declaredType = new FakeReflectionNamedType('\DateTimeImmutable', false, false);

        $type = new DateTimeType();

        $this->assertEquals(
            new DateTime('2020-06-14 14:00:00'),
            $type->fromSchema('2020-06-14 14:00:00', $declaredType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue()
    {
        $this->expectException(TypeException::class);
        $declaredType = new FakeReflectionNamedType('string', false, true);

        $type = new DateTimeType();
        $type->fromSchema(new stdClass(), $declaredType);
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin()
    {
        $this->expectException(TypeException::class);

        $declaredType = new FakeReflectionNamedType('\stdClass', false, false);

        $type = new DateTimeType();
        $type->fromSchema('2020-06-14 14:00:00', $declaredType);
    }

    public function testToSchema()
    {
        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200.));
    }

    public function testToSchemaDateTargetFormat()
    {
        $type = new DateType();

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
