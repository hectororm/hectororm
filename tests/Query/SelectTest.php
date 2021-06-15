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

namespace Hector\Query\Tests;

use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $select = new Select();
        $binding = [];

        $this->assertNull($select->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithoutTable()
    {
        $select = new Select();
        $binding = [];
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');

        $this->assertNull($select->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithTable()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');

        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithDistinct()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');
        $select->distinct(true);

        $this->assertEquals(
            'SELECT DISTINCT * FROM foo AS f',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);

        $select->distinct(false);

        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithTableAndJointures()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');

        $this->assertEquals(
            'SELECT * FROM foo AS f LEFT JOIN bar ON ( bar.bar_id = f.foo_id )',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithColumn()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');
        $select->columns('baz', 'qux');

        $this->assertEquals(
            'SELECT baz, qux FROM foo AS f',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithCondition()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');
        $select->where('baz', '=', 'baz_value');

        $this->assertEquals(
            'SELECT * FROM foo AS f WHERE baz = ?',
            $select->getStatement($binding)
        );
        $this->assertEquals(['baz_value'], $binding);
    }

    public function testGetStatementWithJoinAndConditions()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');
        $select->where('baz', '=', 'baz_value');
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');
        $select
            ->where('bar.bar_column = TIME()')
            ->orWhere('bar.bar_column IS NULL');

        $this->assertEquals(
            'SELECT * FROM foo AS f LEFT JOIN bar ON ( bar.bar_id = f.foo_id ) WHERE baz = ? AND bar.bar_column = TIME() OR bar.bar_column IS NULL',
            $select->getStatement($binding)
        );
        $this->assertEquals(['baz_value'], $binding);
    }

    public function testGetStatementWithGroupAndHaving()
    {
        $select = new Select();
        $binding = [];
        $select
            ->from('`foo`')
            ->where('baz', '=', 'baz_value')
            ->groupBy('baz')
            ->groupByWithRollup()
            ->having('bar', '=', 'bar_value');

        $this->assertEquals(
            'SELECT * FROM `foo` WHERE baz = ? GROUP BY baz WITH ROLLUP HAVING bar = ?',
            $select->getStatement($binding)
        );
        $this->assertEquals(['baz_value', 'bar_value'], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $select = new Select();
        $binding = [];
        $select->from('foo', 'f');

        $this->assertEquals(
            '( SELECT * FROM foo AS f )',
            $select->getStatement($binding, true)
        );
        $this->assertEmpty($binding);
    }
}
