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
use Hector\Query\Component\Group;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testCount()
    {
        $group = new Group();

        $this->assertCount(0, $group);

        $group->groupBy('foo');
        $group->groupBy('bar');

        $this->assertCount(2, $group);
    }

    public function testGetStatement()
    {
        $group = new Group();
        $binds = new BindParamList();

        $this->assertNull($group->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGroupBy()
    {
        $group = new Group();
        $group->groupBy('foo');
        $binds = new BindParamList();

        $this->assertEquals(
            'GROUP BY foo',
            $group->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGroupByRaw()
    {
        $group = new Group();
        $group->groupBy('foo');
        $group->groupBy(new Raw('FUNCTION(:test)', ['test' => 'value']));
        $binds = new BindParamList();

        $this->assertEquals(
            'GROUP BY foo, FUNCTION(:test)',
            $group->getStatement($binds)
        );
        $this->assertEquals(
            ['test' => 'value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWithRollup()
    {
        $group = new Group();
        $group->groupBy('foo');
        $group->groupBy('bar');
        $group->withRollup();
        $binds = new BindParamList();

        $this->assertEquals(
            'GROUP BY foo, bar WITH ROLLUP',
            $group->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }
}
