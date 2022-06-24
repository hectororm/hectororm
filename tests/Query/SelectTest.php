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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class SelectTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $select = new Select();
        $binds = new BindParamList();

        $this->assertNull($select->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutTable()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');

        $this->assertNull($select->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithTable()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithDistinct()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->distinct(true);

        $this->assertEquals(
            'SELECT DISTINCT * FROM foo AS f',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);

        $select->distinct(false);

        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithTableAndJointures()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');

        $this->assertEquals(
            'SELECT * FROM foo AS f LEFT JOIN bar ON ( bar.bar_id = f.foo_id )',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithColumn()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->columns('baz', 'qux');

        $this->assertEquals(
            'SELECT baz, qux FROM foo AS f',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithCondition()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->where('baz', '=', 'baz_value');

        $this->assertEquals(
            'SELECT * FROM foo AS f WHERE baz = :_h_0',
            $select->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithJoinAndConditions()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->where('baz', '=', 'baz_value');
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');
        $select
            ->where('bar.bar_column = TIME()')
            ->orWhere('bar.bar_column IS NULL');

        $this->assertEquals(
            'SELECT * FROM foo AS f LEFT JOIN bar ON ( bar.bar_id = f.foo_id ) WHERE baz = :_h_0 AND bar.bar_column = TIME() OR bar.bar_column IS NULL',
            $select->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithGroupAndHaving()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select
            ->from('`foo`')
            ->where('baz', '=', 'baz_value')
            ->groupBy('baz')
            ->groupByWithRollup()
            ->having('bar', '=', 'bar_value');

        $this->assertEquals(
            'SELECT * FROM `foo` WHERE baz = :_h_0 GROUP BY baz WITH ROLLUP HAVING bar = :_h_1',
            $select->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz_value', '_h_1' => 'bar_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertEquals(
            '( SELECT * FROM foo AS f )',
            $select->getStatement($binds, true)
        );
        $this->assertEmpty($binds);
    }
}
