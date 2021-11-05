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

namespace Hector\Connection\Tests;

use BackedEnum;
use Hector\Connection\BindParam;
use PDO;
use PHPUnit\Framework\TestCase;

class BindParamTest extends TestCase
{
    private function enumCapability(): void
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }
    }

    public function testGetVariable()
    {
        $var = 'foo';
        $param = new BindParam($var, PDO::PARAM_INT);

        $this->assertSame('foo', $param->getVariable());
    }

    public function testGetVariableInteger()
    {
        $var = 1;
        $param = new BindParam($var, PDO::PARAM_INT);

        $this->assertSame(1, $param->getVariable());
    }

    public function testGetVariable_StringEnum()
    {
        $this->enumCapability();

        $var = FakeStringBackedEnum::FOO;
        $param = new BindParam($var);

        $this->assertSame(FakeStringBackedEnum::FOO->value, $param->getVariable());
        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetVariable_IntEnum()
    {
        $this->enumCapability();

        $var = FakeIntBackedEnum::FOO;
        $param = new BindParam($var);

        $this->assertSame(FakeIntBackedEnum::FOO->value, $param->getVariable());
        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataType()
    {
        $var = '1';
        $param = new BindParam($var, PDO::PARAM_INT);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeString()
    {
        $var = 'foo';
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetDataTypeString_enum()
    {
        $this->enumCapability();

        $var = FakeStringBackedEnum::BAR;
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetDataTypeInt()
    {
        $var = 1;
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeInt_enum()
    {
        $this->enumCapability();

        $var = FakeIntBackedEnum::BAR;
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeLob()
    {
        $var = fopen('php://memory', 'r');
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_LOB, $param->getDataType());
    }

    public function testGetDataTypeBool()
    {
        $var = false;
        $param = new BindParam($var);

        $this->assertSame(PDO::PARAM_BOOL, $param->getDataType());
    }
}
