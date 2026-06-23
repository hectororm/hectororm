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
use DateTimeZone;
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

    public function testFromSchemaWithDeclaredTypeBuiltinIntAndInvalidDate(): void
    {
        $this->expectException(ValueException::class);

        $expectedType = new ExpectedType('int', false, true);
        $type = new DateTimeType();

        // strtotime() returns false here; the cast to int must not silently
        // produce 0 (1970-01-01).
        $type->fromSchema('not a date', $expectedType);
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

        // Use the timestamp of the wall-clock string in the ambient timezone so the
        // assertion holds in any timezone (the previous hard-coded 1592143200 only
        // matched under UTC and masked the path inconsistency).
        $timestamp = (new DateTime('2020-06-14 14:00:00'))->getTimestamp();

        $this->assertSame('2020-06-14 14:00:00', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema($timestamp));
        $this->assertSame('2020-06-14 14:00:00', $type->toSchema((float)$timestamp));
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

        $timestamp = (new DateTime('2020-06-14 14:00:00'))->getTimestamp();

        $this->assertSame('2020-06-14', $type->toSchema(new DateTime('2020-06-14 14:00:00')));
        $this->assertSame('2020-06-14', $type->toSchema('2020-06-14 14:00:00'));
        $this->assertSame('2020-06-14', $type->toSchema($timestamp));
        $this->assertSame('2020-06-14', $type->toSchema((float)$timestamp));
    }

    public function testToSchemaWithNotScalar(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->toSchema(['foo']);
    }

    public function testFromSchemaNullWithoutExpected(): void
    {
        $type = new DateTimeType();

        $this->assertNull($type->fromSchema(null));
    }

    public function testFromSchemaNullWithNullableExpected(): void
    {
        $type = new DateTimeType();

        $this->assertNull($type->fromSchema(null, new ExpectedType('\DateTime', true, false)));
    }

    public function testFromSchemaNullWithNonNullableExpected(): void
    {
        $this->expectException(ValueException::class);

        $type = new DateTimeType();
        $type->fromSchema(null, new ExpectedType('\DateTime', false, false));
    }

    public function testToSchemaNull(): void
    {
        $type = new DateTimeType();

        $this->assertNull($type->toSchema(null));
    }

    /**
     * Under a non-UTC ambient timezone, the numeric/timestamp path used to render
     * UTC wall-clock while the string path used local wall-clock, so the two paths
     * disagreed. They must now produce the same instant in the resolved timezone.
     */
    public function testToSchemaPathsAreTimezoneConsistent(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $type = new DateTimeType();

            // Timestamp of the wall-clock string interpreted in the ambient zone.
            $timestamp = (new DateTime('2020-06-14 14:00:00'))->getTimestamp();

            $fromString = $type->toSchema('2020-06-14 14:00:00');
            $fromObject = $type->toSchema(new DateTime('2020-06-14 14:00:00'));
            $fromTimestamp = $type->toSchema($timestamp);
            $fromFloat = $type->toSchema((float)$timestamp);

            $this->assertSame('2020-06-14 14:00:00', $fromString);
            $this->assertSame($fromString, $fromObject);
            $this->assertSame($fromString, $fromTimestamp);
            $this->assertSame($fromString, $fromFloat);
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * An explicit timezone is honoured on every path and is independent of the
     * ambient PHP default timezone.
     */
    public function testToSchemaWithExplicitTimezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');

        try {
            $type = new DateTimeType(timezone: new DateTimeZone('UTC'));

            // 1592143200 == 2020-06-14 14:00:00 UTC.
            $this->assertSame('2020-06-14 14:00:00', $type->toSchema(1592143200));
            $this->assertSame(
                '2020-06-14 14:00:00',
                $type->toSchema(new DateTime('2020-06-14 14:00:00', new DateTimeZone('UTC'))),
            );
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
