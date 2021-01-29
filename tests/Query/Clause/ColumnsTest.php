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

use Hector\Query\Clause\Columns;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    public function testResetColumns()
    {
        $clause = new class {
            use Columns;
        };
        $binding = [];
        $clause->resetColumns();

        $this->assertEmpty($clause->columns->getStatement($binding));

        $clause->column('foo');

        $this->assertNotEmpty($clause->columns->getStatement($binding));

        $clause->resetColumns();

        $this->assertEmpty($clause->columns->getStatement($binding));
    }

    public function testColumn()
    {
        $clause = new class {
            use Columns;
        };
        $binding = [];
        $clause->resetColumns();
        $clause->column('foo', 'f');
        $clause->column('bar', 'b');

        $this->assertEquals(
            '    foo AS f,' . PHP_EOL .
            '    bar AS b' . PHP_EOL,
            $clause->columns->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testColumns()
    {
        $clause = new class {
            use Columns;
        };
        $binding = [];
        $clause->resetColumns();
        $clause->columns('foo', 'bar');

        $this->assertEquals(
            '    foo,' . PHP_EOL .
            '    bar' . PHP_EOL,
            $clause->columns->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }
}
