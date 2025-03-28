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

use Hector\DataTypes\Type\UuidType;
use PHPUnit\Framework\TestCase;

class UuidTypeTest extends TestCase
{
    public function testFromSchema()
    {
        $type = new UuidType();

        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->fromSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->fromSchema('a8184128-7427-48e0-b37d-f7d4891012c6')
        );
        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->fromSchema(hex2bin(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6')))
        );
    }

    public function testToSchemaWithStringFormat()
    {
        $type = new UuidType('string');

        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->toSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->toSchema('a8184128-7427-48e0-b37d-f7d4891012c6')
        );
        $this->assertEquals(
            'a8184128-7427-48e0-b37d-f7d4891012c6',
            $type->toSchema(hex2bin(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6')))
        );
    }

    public function testToSchemaWithHexadecimalFormat()
    {
        $type = new UuidType('hexadecimal');

        $this->assertEquals(
            'a8184128742748e0b37df7d4891012c6',
            $type->toSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
        $this->assertEquals(
            'a8184128742748e0b37df7d4891012c6',
            $type->toSchema('a8184128-7427-48e0-b37d-f7d4891012c6')
        );
        $this->assertEquals(
            'a8184128742748e0b37df7d4891012c6',
            $type->toSchema(hex2bin(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6')))
        );
    }

    public function testToSchemaWithBinaryFormat()
    {
        $type = new UuidType('binary');

        $this->assertEquals(
            hex2bin('a8184128742748e0b37df7d4891012c6'),
            $type->toSchema(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6'))
        );
        $this->assertEquals(
            hex2bin('a8184128742748e0b37df7d4891012c6'),
            $type->toSchema('a8184128-7427-48e0-b37d-f7d4891012c6')
        );
        $this->assertEquals(
            hex2bin('a8184128742748e0b37df7d4891012c6'),
            $type->toSchema(hex2bin(str_replace('-', '', 'a8184128-7427-48e0-b37d-f7d4891012c6')))
        );
    }
}
