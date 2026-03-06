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
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Clause\Assignments;
use Hector\Query\Component\InsertAssignments;
use Hector\Query\Component\UpdateAssignments;
use Hector\Query\Select;
use Hector\Query\Statement\Quoted;
use PHPUnit\Framework\TestCase;

class AssignmentsTest extends TestCase
{
    public function testResetAssignments(): void
    {
        $clause = new class {
            use Assignments;
        };
        $clause->resetAssignments();

        $assignments = $clause->assignments;
        $clause->resetAssignments();

        $this->assertNotSame($assignments, $clause->assignments);
    }

    public function testAssign(): void
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assign('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            UpdateAssignments::createFromAssignments($clause->assignments)->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'bar',
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy()),
        );
    }

    public function testAssigns(): void
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns(['foo' => 'qux', 'bar' => 'baz']);

        $this->assertEquals(
            'foo = :_h_0, bar = :_h_1',
            UpdateAssignments::createFromAssignments($clause->assignments)->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'qux',
                '_h_1' => 'baz'
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy()),
        );
    }

    public function testAssignsSelect(): void
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns(
            (new Select())
                ->from('bar')
                ->where('bar.qux', '=', 1)
        );

        $this->assertEquals(
            'SELECT * FROM bar WHERE bar.qux = :_h_0',
            UpdateAssignments::createFromAssignments($clause->assignments)->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 1,
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy()),
        );
    }

    public function testAssignsTupleFormat(): void
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns([
            [new Quoted('foo'), 'qux'],
            [new Quoted('bar'), 'baz'],
        ]);

        $this->assertSame(
            '`foo` = :_h_0, `bar` = :_h_1',
            UpdateAssignments::createFromAssignments($clause->assignments)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'qux', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy()),
        );
    }

    public function testAssignsTupleWithDriverCapabilities(): void
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('getIdentifierQuote')->willReturn('"');

        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns([
            [new Quoted('foo'), 'bar'],
        ]);

        $this->assertSame(
            '"foo" = :_h_0',
            UpdateAssignments::createFromAssignments($clause->assignments)->getStatement($binds, $capabilities)
        );
    }

    public function testAssignsTupleInsert(): void
    {
        $clause = new class {
            use Assignments;
        };
        $binds = new BindParamList();
        $clause->resetAssignments();

        $clause->assigns([
            [new Quoted('foo'), 'qux'],
            [new Quoted('bar'), 'baz'],
        ]);

        $this->assertSame(
            '( `foo`, `bar` ) VALUES ( :_h_0, :_h_1 )',
            InsertAssignments::createFromAssignments($clause->assignments)->getStatement($binds)
        );
    }
}
