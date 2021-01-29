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
        $binding = [];

        $this->assertNull($group->getStatement($binding));
        $this->assertEquals([], $binding);
    }

    public function testGroupBy()
    {
        $group = new Group();
        $group->groupBy('foo');
        $binding = [];

        $this->assertEquals(
            'GROUP BY' . PHP_EOL .
            '    foo' . PHP_EOL,
            $group->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testGroupByRaw()
    {
        $group = new Group();
        $group->groupBy('foo');
        $group->groupBy(new Raw('FUNCTION(?)', ['value']));
        $binding = [];

        $this->assertEquals(
            'GROUP BY' . PHP_EOL .
            '    foo,' . PHP_EOL .
            '    FUNCTION(?)' . PHP_EOL,
            $group->getStatement($binding)
        );
        $this->assertEquals(['value'], $binding);
    }

    public function testWithRollup()
    {
        $group = new Group();
        $group->groupBy('foo');
        $group->groupBy('bar');
        $group->withRollup();
        $binding = [];

        $this->assertEquals(
            'GROUP BY' . PHP_EOL .
            '    foo,' . PHP_EOL .
            '    bar' . PHP_EOL .
            '    WITH ROLLUP' . PHP_EOL,
            $group->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }
}
