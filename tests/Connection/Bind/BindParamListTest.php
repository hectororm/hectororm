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

use Hector\Connection\Bind\BindParamList;
use PDO;
use PHPUnit\Framework\TestCase;
use ValueError;

class BindParamListTest extends TestCase
{
    public function testAdd(): void
    {
        $binds = new BindParamList();
        $bind = $binds->add('foo', 'name');

        $this->assertEquals('name', $bind->getName());
        $this->assertEquals('foo', $bind->getValue());
        $this->assertEquals(PDO::PARAM_STR, $bind->getDataType());
    }

    public function testAdd_intName(): void
    {
        $binds = new BindParamList();
        $bind = $binds->add('foo', name: 3);

        $this->assertEquals(3, $bind->getName());
    }

    public function testAdd_intName_zero(): void
    {
        $this->expectException(ValueError::class);
        $binds = new BindParamList();
        $binds->add('foo', name: 0);
    }

    public function testAdd_nullName(): void
    {
        $binds = new BindParamList();
        $bind = $binds->add('foo');

        $this->assertEquals('_h_0', $bind->getName());
    }

    public function testGetArrayCopy(): void
    {
        $binds = new BindParamList(['foo' => 'bar', 'qux' => 'baz']);

        $arr = $binds->getArrayCopy();
        $this->assertIsArray($arr);
        $this->assertCount(2, $arr);
    }

    public function testGetIterator(): void
    {
        $binds = new BindParamList(['foo' => 'bar', 'qux' => 'baz']);

        $this->assertEquals($binds->getArrayCopy(), $binds->getIterator()->getArrayCopy());
    }

    public function testCount(): void
    {
        $binds = new BindParamList(['foo' => 'bar', 'qux' => 'baz']);

        $this->assertCount(2, $binds);
    }

    public function testCount_empty(): void
    {
        $binds = new BindParamList();

        $this->assertCount(0, $binds);
    }

    public function testReset(): void
    {
        $binds = new BindParamList(['foo' => 'bar', 'qux' => 'baz']);

        $this->assertCount(2, $binds);

        $binds->reset();

        $this->assertCount(0, $binds);
    }
}
