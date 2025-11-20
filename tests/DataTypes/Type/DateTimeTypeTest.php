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
use DateTimeImmutable;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\DateTimeType;
use PHPUnit\Framework\TestCase;
use stdClass;

class DateTimeTypeTest extends TestCase
{
    public function testFromSchema(): void
    {
        $type = new DateTimeType();

        $this->assertEquals(new DateTime('2020-06-14 14:00:00'), $type->fromSchema('2020-06-14 14:00:00'));
    }

    public function testFromSchemaWithBadFormat(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->fromSchema('BAD @FORMAT');
    }

    public function testFromSchemaWithNotValid(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->fromSchema(1);
    }

    public function testFromSchemaWithNotScalar(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinString(): void
    {
        $expectedType = new ExpectedType('string', false, true);

        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->fromSchema('2020-06-14 14:00:00', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinInt(): void
    {
        $expectedType = new ExpectedType('int', false, true);

        $type = new DateTimeType();

        $this->assertSame(
            (new DateTime('2020-06-14 14:00:00'))->getTimestamp(),
            $type->fromSchema('2020-06-14 14:00:00', $expectedType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinDateTime(): void
    {
        $expectedType = new ExpectedType('\DateTimeImmutable', false, false);

        $type = new DateTimeType();

        $this->assertEquals(
            new DateTime('2020-06-14 14:00:00'),
            $type->fromSchema('2020-06-14 14:00:00', $expectedType)
        );
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue(): void
    {
        $this->expectException(ValueException::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new DateTimeType();
        $type->fromSchema(new stdClass(), $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin(): void
    {
        $this->expectException(ValueException::class);

        $expectedType = new ExpectedType('\stdClass', false, false);

        $type = new DateTimeType();
        $type->fromSchema('2020-06-14 14:00:00', $expectedType);
    }

    public function testFromSchema_targetClass(): void
    {
        $type = new DateTimeType(class: DateTimeImmutable::class);

        $this->assertInstanceOf(DateTimeImmutable::class, $type->fromSchema('2021-11-05'));
        $this->assertEquals('2021-11-05', $type->fromSchema('2021-11-05')->format('Y-m-d'));
    }

    public function testToSchema(): void
    {
        $type = new DateTimeType();

        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200.));
    }

    public function testToSchemaWithBadFormat(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType('BAD FORMAT');
        $type->toSchema('Y-m-d H:i:s');
    }

    public function testToSchemaDateTargetFormat(): void
    {
        $type = new DateTimeType('Y-m-d');

        $this->assertSame('2020-06-14', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14', $type->toSchema(1592143200));
        $this->assertSame('2020-06-14', $type->toSchema(1592143200.));
    }

    public function testToSchemaWithNotScalar(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->toSchema(['foo']);
    }
}
