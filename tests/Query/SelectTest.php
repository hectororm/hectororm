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
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    DISTINCT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL,
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);

        $select->distinct(false);

        $this->assertEquals(
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL .
            'LEFT JOIN bar' . PHP_EOL .
            '    ON ( bar.bar_id = f.foo_id )' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    baz,' . PHP_EOL .
            '    qux' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    baz = ?' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    foo AS f' . PHP_EOL .
            'LEFT JOIN bar' . PHP_EOL .
            '    ON ( bar.bar_id = f.foo_id )' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    baz = ?' . PHP_EOL .
            '    AND bar.bar_column = TIME()' . PHP_EOL .
            '    OR bar.bar_column IS NULL' . PHP_EOL,
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
            'SELECT' . PHP_EOL .
            '    *' . PHP_EOL .
            'FROM' . PHP_EOL .
            '    `foo`' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    baz = ?' . PHP_EOL .
            'GROUP BY' . PHP_EOL .
            '    baz' . PHP_EOL .
            '    WITH ROLLUP' . PHP_EOL .
            'HAVING' . PHP_EOL .
            '    bar = ?' . PHP_EOL,
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
            '(' . PHP_EOL .
            '    SELECT' . PHP_EOL .
            '        *' . PHP_EOL .
            '    FROM' . PHP_EOL .
            '        foo AS f' . PHP_EOL .
            ')',
            $select->getStatement($binding, true)
        );
        $this->assertEmpty($binding);
    }
}
