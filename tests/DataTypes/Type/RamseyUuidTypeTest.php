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

use Hector\DataTypes\Type\RamseyUuidType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RamseyUuidTypeTest extends TestCase
{
    public function testFromSchema()
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

    public function testToSchema()
    {
        $type = new RamseyUuidType();

        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->toSchema(Uuid::fromString('a8184128-7427-48e0-b37d-f7d4891012c6')),
        );
    }
}
