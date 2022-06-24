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
use Hector\Query\Component\Order;
use Hector\Query\Delete;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $delete = new Delete();
        $binds = new BindParamList();

        $this->assertNull($delete->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutCondition()
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('`foo`');

        $this->assertEquals(
            'DELETE FROM `foo`',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('`foo`');

        $this->assertEquals(
            '( DELETE FROM `foo` )',
            $delete->getStatement($binds, true)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithCondition()
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('`foo`');
        $delete->where('`bar`', '=', 'value');

        $this->assertEquals(
            'DELETE FROM `foo` WHERE `bar` = :_h_0',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithLimit()
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('`foo`');
        $delete->where('`bar`', '=', 'value');
        $delete->limit(2, 5);

        $this->assertEquals(
            'DELETE FROM `foo` WHERE `bar` = :_h_0 LIMIT 2 OFFSET 5',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithOrderAndLimit()
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('`foo`');
        $delete->orderBy('`bar`', Order::ORDER_DESC);
        $delete->limit(2, 5);

        $this->assertEquals(
            'DELETE FROM `foo` ORDER BY `bar` DESC LIMIT 2 OFFSET 5',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
