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

use BcMath\Number;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\DecimalType;
use PHPUnit\Framework\TestCase;
use stdClass;

class DecimalTypeTest extends TestCase
{
    public function testFromSchemaKeepsExactStringWithoutFloat(): void
    {
        $type = new DecimalType();

        // These would lose precision if cast to a PHP float.
        $this->assertSame('12345678901234567890.1234', $type->fromSchema('12345678901234567890.1234'));
        $this->assertSame('99999999999999.99', $type->fromSchema('99999999999999.99'));
        $this->assertSame('1.50', $type->fromSchema('1.50'));
        $this->assertSame('-42', $type->fromSchema('-42'));
    }

    public function testFromSchemaAcceptsNumericScalars(): void
    {
        $type = new DecimalType();

        $this->assertSame('1', $type->fromSchema(1));
        $this->assertSame('1.5', $type->fromSchema(1.5));
    }

    public function testFromSchemaRejectsNonNumericString(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->fromSchema('abc');
    }

    public function testFromSchemaRejectsBool(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->fromSchema(true);
    }

    public function testFromSchemaRejectsNotScalar(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->fromSchema(new stdClass());
    }

    public function testFromSchemaRejectsNotBuiltinExpected(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->fromSchema('1.5', new ExpectedType(stdClass::class, false, false));
    }

    public function testToSchemaKeepsExactString(): void
    {
        $type = new DecimalType();

        $this->assertSame('99999999999999.99', $type->toSchema('99999999999999.99'));
        $this->assertSame('1', $type->toSchema(1));
        $this->assertSame('1.5', $type->toSchema(1.5));
    }

    public function testToSchemaRejectsNonNumericString(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->toSchema('not a number');
    }

    public function testToSchemaNull(): void
    {
        $this->assertNull((new DecimalType())->toSchema(null));
    }

    public function testFromSchemaNullWithoutExpected(): void
    {
        $this->assertNull((new DecimalType())->fromSchema(null));
    }

    public function testFromSchemaNullWithNonNullableExpected(): void
    {
        $this->expectException(ValueException::class);

        (new DecimalType())->fromSchema(null, new ExpectedType('string', false, true));
    }

    public function testEqualsTreatsEquivalentRepresentationsAsEqual(): void
    {
        $type = new DecimalType();

        $this->assertTrue($type->equals('1.50', '1.5'));
        $this->assertTrue($type->equals('+1', '1'));
        $this->assertTrue($type->equals('1.0', '1'));
        $this->assertTrue($type->equals('-0.50', '-0.5'));
        $this->assertTrue($type->equals('0', '0.00'));
        $this->assertTrue($type->equals(null, null));
    }

    public function testEqualsDetectsRealChanges(): void
    {
        $type = new DecimalType();

        $this->assertFalse($type->equals('1.50', '1.51'));
        $this->assertFalse($type->equals('1', '-1'));
        $this->assertFalse($type->equals(null, '0'));
        $this->assertFalse($type->equals('0', null));
    }

    public function testFromSchemaHydratesBcMathNumberWhenExpected(): void
    {
        if (false === class_exists(Number::class)) {
            $this->markTestSkipped('BcMath\Number requires PHP 8.4+');
        }

        $type = new DecimalType();
        $result = $type->fromSchema('99999999999999.99', new ExpectedType(Number::class, false, false));

        $this->assertInstanceOf(Number::class, $result);
        $this->assertSame('99999999999999.99', (string)$result);
    }

    public function testToSchemaAcceptsBcMathNumber(): void
    {
        if (false === class_exists(Number::class)) {
            $this->markTestSkipped('BcMath\Number requires PHP 8.4+');
        }

        $type = new DecimalType();

        $this->assertSame('99999999999999.99', $type->toSchema(new Number('99999999999999.99')));
    }
}
