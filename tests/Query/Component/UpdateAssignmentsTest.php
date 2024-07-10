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
use Hector\Query\Component\UpdateAssignments;
use Hector\Query\Select;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class UpdateAssignmentsTest extends TestCase
{
    public function testGetStatement()
    {
        $assignments = new UpdateAssignments();
        $binds = new BindParamList();

        $this->assertNull($assignments->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testAssignmentOne()
    {
        $assignments = new UpdateAssignments();
        $assignments->assignment('foo', 'value');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0',
            $assignments->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAssignmentTwo()
    {
        $assignments = new UpdateAssignments();
        $assignments->assignment('foo', 'value');
        $assignments->assignment('bar', 'value2');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0, bar = :_h_1',
            $assignments->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value', '_h_1' => 'value2'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAssignments()
    {
        $assignments = new UpdateAssignments();
        $assignments->assignment('foo', 'value');
        $assignments->assignments(
            [
                '`baz`' => new Raw('UNIX_TIMESTAMP(?)', ['2020-04-10']),
                'qux' => 'value3'
            ]
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0, `baz` = UNIX_TIMESTAMP(?), qux = :_h_1',
            $assignments->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value', '2020-04-10', '_h_1' => 'value3'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAssignStatement()
    {
        $assignments = new UpdateAssignments();
        $assignments->assignments(
            (new Select())
                ->from('bar')
                ->where('bar.qux', '=', 1)
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'SELECT * FROM bar WHERE bar.qux = :_h_0',
            $assignments->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAssignNull()
    {
        $assignments = new UpdateAssignments();
        $assignments->assignments(
            [
                '`foo`' => null,
                'bar' => 'baz'
            ]
        );
        $binds = new BindParamList();

        $this->assertEquals(
            '`foo` = :_h_0, bar = :_h_1',
            $assignments->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => null, '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
