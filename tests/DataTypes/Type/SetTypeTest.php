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

use stdClass;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\SetType;
use PHPUnit\Framework\TestCase;

class SetTypeTest extends TestCase
{
    public function testFromSchema(): void
    {
        $type = new SetType();

        $this->assertEquals(['foo', 'bar'], $type->fromSchema('foo, bar'));
    }

    public function testFromSchemaWithNotValid(): void
    {
        $this->expectException(ValueException::class);

        $type = new SetType();
        $type->fromSchema(1);
    }

    public function testFromSchemaWithNotScalar(): void
    {
        $this->expectException(ValueException::class);

        $type = new SetType();
        $type->fromSchema(['foo']);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinArray(): void
    {
        $expectedType = new ExpectedType('array', false, true);
        $type = new SetType();

        $this->assertEquals(['foo', 'bar'], $type->fromSchema('foo, bar', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinString(): void
    {
        $expectedType = new ExpectedType('string', false, true);
        $type = new SetType();

        $this->assertEquals('foo,bar', $type->fromSchema('foo, bar', $expectedType));
    }

    public function testFromSchemaWithDeclaredTypeBuiltinInvalid(): void
    {
        $this->expectException(ValueException::class);

        $expectedType = new ExpectedType('int', false, true);
        $type = new SetType();

        $type->fromSchema('foo, bar', $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeBuiltinAndBadValue(): void
    {
        $this->expectException(ValueException::class);
        $expectedType = new ExpectedType('string', false, true);

        $type = new SetType();
        $type->fromSchema(new stdClass(), $expectedType);
    }

    public function testFromSchemaWithDeclaredTypeNotBuiltin(): void
    {
        $this->expectException(ValueException::class);

        $expectedType = new ExpectedType('\stdClass', false, false);

        $type = new SetType();
        $type->fromSchema('foo, bar', $expectedType);
    }

    public function testToSchema(): void
    {
        $type = new SetType();

        $this->assertSame('foo,bar', $type->toSchema(['foo', 'bar']));
    }

    public function testToSchemaWithBadType(): void
    {
        $this->expectException(ValueException::class);

        $type = new SetType();
        $fakeObject = new class {
        };

        $type->toSchema($fakeObject);
    }

    public function testEquals(): void
    {
        $type = new SetType();

        $this->assertTrue($type->equals('foo,bar,baz', 'baz,foo,bar'));
        $this->assertFalse($type->equals('foo,bar,baz', 'foo,bar'));
        $this->assertFalse($type->equals('foo,baz', 'foo,bar,baz'));
    }
}
