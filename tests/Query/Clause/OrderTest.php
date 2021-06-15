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

namespace Hector\Query\Tests\Clause;

use Hector\Query\Clause\Order;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testResetOrder()
    {
        $clause = new class {
            use Order;
        };
        $binding = [];
        $clause->resetOrder();

        $this->assertEmpty($clause->order->getStatement($binding));

        $clause->orderBy('foo');

        $this->assertNotEmpty($clause->order->getStatement($binding));

        $clause->resetOrder();

        $this->assertEmpty($clause->order->getStatement($binding));
    }

    public function testOrderBy()
    {
        $clause = new class {
            use Order;
        };
        $binding = [];
        $clause->resetOrder();
        $clause->orderBy('foo');
        $clause->orderBy('bar', \Hector\Query\Component\Order::ORDER_DESC);

        $this->assertEquals(
            'ORDER BY foo, bar DESC',
            $clause->order->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testRandom()
    {
        $clause = new class {
            use Order;
        };
        $binding = [];
        $clause->resetOrder();
        $clause->random();

        $this->assertEquals(
            'ORDER BY RAND()',
            $clause->order->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }
}
