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
use Hector\Query\Component\Table;
use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testGetStatement()
    {
        $table = new Table();
        $binds = new BindParamList();

        $this->assertNull($table->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testTableOne()
    {
        $table = new Table();
        $table->table('foo', 'f');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo AS f',
            $table->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testTableTwo()
    {
        $table = new Table();
        $table->table('foo', 'f');
        $table->table('bar', 'b');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo AS f, bar AS b',
            $table->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testTableWithStatement()
    {
        $table = new Table();
        $table->table('foo', 'f');
        $table->table(
            (new Select())
                ->from('bar')
                ->where('bar.qux', '=', 1),
            'table'
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'foo AS f, ( SELECT * FROM bar WHERE bar.qux = :_h_0 ) AS table',
            $table->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
