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

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\Group;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testResetGroup()
    {
        $clause = new class {
            use Group;
        };
        $binds = new BindParamList();
        $clause->resetGroup();

        $this->assertEmpty($clause->group->getStatement($binds));

        $clause->groupBy('foo');

        $this->assertNotEmpty($clause->group->getStatement($binds));

        $clause->resetGroup();

        $this->assertEmpty($clause->group->getStatement($binds));
    }

    public function testGroupBy()
    {
        $clause = new class {
            use Group;
        };
        $binds = new BindParamList();
        $clause->resetGroup();
        $clause->groupBy('foo');
        $clause->groupBy('bar');

        $this->assertEquals(
            'GROUP BY foo, bar',
            $clause->group->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGroupByWithRollup()
    {
        $clause = new class {
            use Group;
        };
        $binds = new BindParamList();
        $clause->resetGroup();
        $clause->groupBy('foo');
        $clause->groupBy('bar');
        $clause->groupByWithRollup(true);

        $this->assertEquals(
            'GROUP BY foo, bar WITH ROLLUP',
            $clause->group->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }
}
