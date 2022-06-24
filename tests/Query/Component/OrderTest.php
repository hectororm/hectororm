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

namespace Hector\Query\Tests\Component;

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Component\Order;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testCount()
    {
        $orderBy = new Order();

        $this->assertCount(0, $orderBy);

        $orderBy->orderBy('foo');
        $orderBy->orderBy('bar');

        $this->assertCount(2, $orderBy);
    }

    public function testGetStatement()
    {
        $orderBy = new Order();
        $binds = new BindParamList();

        $this->assertNull($orderBy->getStatement($binds));
    }

    public function testOrderByOne()
    {
        $orderBy = new Order();
        $orderBy->orderBy('foo', Order::ORDER_DESC);
        $binds = new BindParamList();

        $this->assertEquals(
            'ORDER BY foo DESC',
            $orderBy->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrderByTwo()
    {
        $orderBy = new Order();
        $orderBy->orderBy('foo', Order::ORDER_DESC);
        $orderBy->orderBy('bar');
        $binds = new BindParamList();

        $this->assertEquals(
            'ORDER BY foo DESC, bar',
            $orderBy->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrderByAsc()
    {
        $orderBy = new Order();
        $orderBy->orderBy('baz', Order::ORDER_ASC);
        $binds = new BindParamList();

        $this->assertEquals(
            'ORDER BY baz ASC',
            $orderBy->getStatement($binds)
        );
    }

    public function testOrderByStatement()
    {
        $orderBy = new Order();
        $orderBy->orderBy('baz', Order::ORDER_ASC);
        $orderBy->orderBy(new Raw('IF(:_h_0 IS NULL, 1, 0)', ['foo']));
        $binds = new BindParamList();

        $this->assertEquals(
            'ORDER BY baz ASC, IF(:_h_0 IS NULL, 1, 0)',
            $orderBy->getStatement($binds)
        );
        $this->assertEquals(
            ['foo'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
