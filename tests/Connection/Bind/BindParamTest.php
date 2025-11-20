<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Connection\Tests\Bind;

use BackedEnum;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Tests\FakeIntBackedEnum;
use Hector\Connection\Tests\FakeStringBackedEnum;
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

    public function testGetName(): void
    {
        $param = new BindParam('bar', 'foo', PDO::PARAM_INT);

        $this->assertSame('bar', $param->getName());
    }

    public function testGetValue(): void
    {
        $param = new BindParam('name', 'foo', PDO::PARAM_INT);

        $this->assertSame('foo', $param->getValue());
    }

    public function testGetVariableInteger(): void
    {
        $param = new BindParam('name', 1, PDO::PARAM_INT);

        $this->assertSame(1, $param->getValue());
    }

    public function testGetVariable_StringEnum(): void
    {
        $this->enumCapability();

        $var = FakeStringBackedEnum::FOO;
        $param = new BindParam('name', $var);

        $this->assertSame(FakeStringBackedEnum::FOO->value, $param->getValue());
        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetVariable_IntEnum(): void
    {
        $this->enumCapability();

        $var = FakeIntBackedEnum::FOO;
        $param = new BindParam('name', $var);

        $this->assertSame(FakeIntBackedEnum::FOO->value, $param->getValue());
        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataType(): void
    {
        $var = '1';
        $param = new BindParam('name', $var, PDO::PARAM_INT);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeString(): void
    {
        $var = 'foo';
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetDataTypeString_enum(): void
    {
        $this->enumCapability();

        $var = FakeStringBackedEnum::BAR;
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_STR, $param->getDataType());
    }

    public function testGetDataTypeInt(): void
    {
        $var = 1;
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeInt_enum(): void
    {
        $this->enumCapability();

        $var = FakeIntBackedEnum::BAR;
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_INT, $param->getDataType());
    }

    public function testGetDataTypeLob(): void
    {
        $var = fopen('php://memory', 'r');
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_LOB, $param->getDataType());
    }

    public function testGetDataTypeBool(): void
    {
        $var = false;
        $param = new BindParam('name', $var);

        $this->assertSame(PDO::PARAM_BOOL, $param->getDataType());
    }
}
