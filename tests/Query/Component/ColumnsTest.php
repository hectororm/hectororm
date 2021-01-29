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

use Hector\Query\Component\Columns;
use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class ColumnsTest extends TestCase
{
    public function testCount()
    {
        $columns = new Columns();

        $this->assertCount(0, $columns);

        $columns->column('foo', 'f');
        $columns->columns('bar', 'baz');

        $this->assertCount(3, $columns);
    }

    public function testGetStatement()
    {
        $columns = new Columns();
        $binding = [];

        $this->assertNull($columns->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testColumn()
    {
        $columns = new Columns();
        $columns->column('foo', 'f');
        $binding = [];

        $this->assertEquals('    foo AS f' . PHP_EOL, $columns->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testTwoColumn()
    {
        $columns = new Columns();
        $columns->column('foo', 'f');
        $columns->column('bar');
        $binding = [];

        $this->assertEquals(
            '    foo AS f,' . PHP_EOL .
            '    bar' . PHP_EOL,
            $columns->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testColumns()
    {
        $columns = new Columns();
        $columns->column('bar');
        $columns->columns('baz', 'qux');
        $binding = [];

        $this->assertEquals(
            '    bar,' . PHP_EOL .
            '    baz,' . PHP_EOL .
            '    qux' . PHP_EOL,
            $columns->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testColumnWithStatement()
    {
        $columns = new Columns();
        $columns->column(
            (new Select())
                ->from('bar')
                ->where('bar.qux', '=', 1),
            'b'
        );
        $columns->columns('foo', 'baz');
        $binding = [];

        $this->assertEquals(
            '    (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            bar' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar.qux = ?' . PHP_EOL .
            '    ) AS b,' . PHP_EOL .
            '    foo,' . PHP_EOL .
            '    baz' . PHP_EOL,
            $columns->getStatement($binding)
        );
        $this->assertEquals([1], $binding);
    }
}
