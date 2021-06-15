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

use Hector\Query\Component\Assignments;
use Hector\Query\Select;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class AssignmentsTest extends TestCase
{
    public function testGetStatement()
    {
        $assignments = new Assignments();
        $binding = [];

        $this->assertNull($assignments->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testAssignmentOne()
    {
        $assignments = new Assignments();
        $assignments->assignment('foo', 'value');
        $binding = [];

        $this->assertEquals(
            'foo = ?',
            $assignments->getStatement($binding)
        );
        $this->assertEquals(['value'], $binding);
    }

    public function testAssignmentTwo()
    {
        $assignments = new Assignments();
        $assignments->assignment('foo', 'value');
        $assignments->assignment('bar', 'value2');
        $binding = [];

        $this->assertEquals(
            'foo = ?, bar = ?',
            $assignments->getStatement($binding)
        );
        $this->assertEquals(['value', 'value2'], $binding);
    }

    public function testAssignments()
    {
        $assignments = new Assignments();
        $assignments->assignment('foo', 'value');
        $assignments->assignments(
            [
                '`baz`' => new Raw('UNIX_TIMESTAMP(?)', ['2020-04-10']),
                'qux' => 'value3'
            ]
        );
        $binding = [];

        $this->assertEquals(
            'foo = ?, `baz` = UNIX_TIMESTAMP(?), qux = ?',
            $assignments->getStatement($binding)
        );
        $this->assertEquals(['value', '2020-04-10', 'value3'], $binding);
    }

    public function testAssignStatement()
    {
        $assignments = new Assignments();
        $assignments->assignments(
            [
                (new Select())
                    ->from('bar')
                    ->where('bar.qux', '=', 1)
            ]
        );
        $binding = [];

        $this->assertEquals(
            '( SELECT * FROM bar WHERE bar.qux = ? )',
            $assignments->getStatement($binding)
        );
        $this->assertEquals([1], $binding);
    }

    public function testAssignNull()
    {
        $assignments = new Assignments();
        $assignments->assignments(
            [
                '`foo`' => null,
                'bar' => 'baz'
            ]
        );
        $binding = [];

        $this->assertEquals(
            '`foo` = ?, bar = ?',
            $assignments->getStatement($binding)
        );
        $this->assertEquals([null, 'baz'], $binding);
    }
}
