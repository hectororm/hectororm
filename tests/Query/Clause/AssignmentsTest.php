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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\Assignments;
use PHPUnit\Framework\TestCase;

class AssignmentsTest extends TestCase
{
    public function testResetAssignments()
    {
        $clause = new class {
            use Assignments;
        };
        $clause->resetAssignments();

        $assignments = $clause->assignments;
        $clause->resetAssignments();

        $this->assertNotSame($assignments, $clause->assignments);
    }

    public function testAssign()
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assign('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            $clause->assignments->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'bar',
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy()),
        );
    }

    public function testAssigns()
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns(['foo' => 'qux', 'bar' => 'baz']);

        $this->assertEquals(
            'foo = :_h_0, bar = :_h_1',
            $clause->assignments->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'qux',
                '_h_1' => 'baz'
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy()),
        );
    }
}
