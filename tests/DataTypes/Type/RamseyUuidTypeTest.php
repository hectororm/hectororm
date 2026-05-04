<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\DataTypes\Tests\Type;

use stdClass;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\RamseyUuidType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RamseyUuidTypeTest extends TestCase
{
    public function testFromSchema(): void
    {
        $type = new RamseyUuidType();

        $this->assertInstanceOf(
            UuidInterface::class,
            $type->fromSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
        $this->assertEquals(
            Uuid::fromString('a8184128-7427-48e0-b37d-f7d4891012c6'),
            $type->fromSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
    }

    public function testFromSchemaWithUuidInterfaceExpected(): void
    {
        $type = new RamseyUuidType();
        $expected = ExpectedType::from(UuidInterface::class);

        $result = $type->fromSchema(
            str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'),
            $expected,
        );

        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertEquals(
            Uuid::fromString('a8184128-7427-48e0-b37d-f7d4891012c6'),
            $result,
        );
    }

    public function testFromSchemaWithBuiltinExpectedThrows(): void
    {
        $type = new RamseyUuidType();
        $expected = ExpectedType::from('string');

        $this->expectException(ValueException::class);

        $type->fromSchema(
            str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'),
            $expected,
        );
    }

    public function testFromSchemaWithIncompatibleClassExpectedThrows(): void
    {
        $type = new RamseyUuidType();
        $expected = ExpectedType::from(stdClass::class);

        $this->expectException(ValueException::class);

        $type->fromSchema(
            str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'),
            $expected,
        );
    }

    public function testToSchema(): void
    {
        $type = new RamseyUuidType();

        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->toSchema(Uuid::fromString('a8184128-7427-48e0-b37d-f7d4891012c6')),
        );
    }
}
