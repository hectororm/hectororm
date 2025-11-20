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
use Hector\Query\Update;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{
    public function testGetStatementEmpty(): void
    {
        $update = new Update();
        $binds = new BindParamList();

        $this->assertNull($update->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');

        $this->assertNull($update->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithOneAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithTwoAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0, baz = :_h_1',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'value_bar',
                '_h_1' => 'value_baz'
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithConditions(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');
        $update
            ->where('foo.foo_column', '=', 1)
            ->orWhere('foo.foo_column IS NULL');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0, baz = :_h_1 WHERE foo.foo_column = :_h_2 OR foo.foo_column IS NULL',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'value_bar',
                '_h_1' => 'value_baz',
                '_h_2' => 1
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            '( UPDATE foo SET bar = :_h_0 )',
            $update->getStatement($binds, true)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
