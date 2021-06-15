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

use Hector\Query\Component\Table;
use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testGetStatement()
    {
        $table = new Table();
        $binding = [];

        $this->assertNull($table->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testTableOne()
    {
        $table = new Table();
        $table->table('foo', 'f');
        $binding = [];

        $this->assertEquals(
            'foo AS f',
            $table->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testTableTwo()
    {
        $table = new Table();
        $table->table('foo', 'f');
        $table->table('bar', 'b');
        $binding = [];

        $this->assertEquals(
            'foo AS f, bar AS b',
            $table->getStatement($binding)
        );
        $this->assertEmpty($binding);
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
        $binding = [];

        $this->assertEquals(
            'foo AS f, ( SELECT * FROM bar WHERE bar.qux = ? ) AS table',
            $table->getStatement($binding)
        );
        $this->assertEquals([1], $binding);
    }
}
