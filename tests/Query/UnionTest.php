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
use Hector\Query\Union;
use PHPUnit\Framework\TestCase;

class UnionTest extends TestCase
{
    public function testStatementEmpty()
    {
        $binds = new BindParamList();
        $union = new Union();

        $this->assertNull($union->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testStatement()
    {
        $binds = new BindParamList();
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union->addSelect($select, $select2);

        $this->assertEquals(
            '( SELECT * FROM foo AS f WHERE bar = :_h_0 )' .
            ' UNION DISTINCT ' .
            '( SELECT * FROM foo2 AS f WHERE bar = :_h_1 )',
            $union->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testStatementEncapsulate()
    {
        $binds = new BindParamList();
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->addSelect($select)
            ->addSelect($select2);

        $this->assertEquals(
            '( ' .
            '( SELECT * FROM foo AS f WHERE bar = :_h_0 )' .
            ' UNION DISTINCT ' .
            '( SELECT * FROM foo2 AS f WHERE bar = :_h_1 )' .
            ' )',
            $union->getStatement($binds, true)
        );
        $this->assertEquals(
            ['_h_0' => 'baz', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testStatementUnionAll()
    {
        $binds = new BindParamList();
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->all()
            ->addSelect($select)
            ->addSelect($select2);

        $this->assertEquals(
            '( SELECT * FROM foo AS f WHERE bar = :_h_0 )' .
            ' UNION ALL ' .
            '( SELECT * FROM foo2 AS f WHERE bar = :_h_1 )',
            $union->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testStatementWithOrderAndLimit()
    {
        $binds = new BindParamList();
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->all()
            ->addSelect($select)
            ->addSelect($select2)
            ->limit(10)
            ->orderBy('a');

        $this->assertEquals(
            '( ' .
            '( SELECT * FROM foo AS f WHERE bar = :_h_0 )' .
            ' UNION ALL ' .
            '( SELECT * FROM foo2 AS f WHERE bar = :_h_1 )' .
            ' ) ' .
            'ORDER BY a LIMIT 10',
            $union->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
