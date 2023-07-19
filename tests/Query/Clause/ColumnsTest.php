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

namespace Hector\Query\Tests\Clause;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\Columns;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    public function testResetColumns()
    {
        $clause = new class {
            use Columns;
        };
        $binds = new BindParamList();
        $clause->resetColumns();

        $this->assertEmpty($clause->columns->getStatement($binds));

        $clause->column('foo');

        $this->assertNotEmpty($clause->columns->getStatement($binds));

        $clause->resetColumns();

        $this->assertEmpty($clause->columns->getStatement($binds));
    }

    public function testColumn()
    {
        $clause = new class {
            use Columns;
        };
        $binds = new BindParamList();
        $clause->resetColumns();
        $clause->column('foo', 'f');
        $clause->column('bar', 'b');

        $this->assertEquals(
            'foo AS `f`, bar AS `b`',
            $clause->columns->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testColumns()
    {
        $clause = new class {
            use Columns;
        };
        $binds = new BindParamList();
        $clause->resetColumns();
        $clause->columns('foo', 'bar');

        $this->assertEquals(
            'foo, bar',
            $clause->columns->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }
}
