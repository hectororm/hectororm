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

use Hector\Query\Component\Order;
use Hector\Query\Delete;
use PHPUnit\Framework\TestCase;

class DeleteTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $delete = new Delete();
        $binding = [];

        $this->assertNull($delete->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithoutCondition()
    {
        $delete = new Delete();
        $binding = [];
        $delete->from('`foo`');

        $this->assertEquals(
            'DELETE FROM' . PHP_EOL .
            '    `foo`' . PHP_EOL,
            $delete->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $delete = new Delete();
        $binding = [];
        $delete->from('`foo`');

        $this->assertEquals(
            '(' . PHP_EOL .
            '    DELETE FROM' . PHP_EOL .
            '        `foo`' . PHP_EOL .
            ')',
            $delete->getStatement($binding, true)
        );
        $this->assertEquals([], $binding);
    }

    public function testGetStatementWithCondition()
    {
        $delete = new Delete();
        $binding = [];
        $delete->from('`foo`');
        $delete->where('`bar`', '=', 'value');

        $this->assertEquals(
            'DELETE FROM' . PHP_EOL .
            '    `foo`' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    `bar` = ?' . PHP_EOL,
            $delete->getStatement($binding)
        );
        $this->assertEquals(['value'], $binding);
    }

    public function testGetStatementWithLimit()
    {
        $delete = new Delete();
        $binding = [];
        $delete->from('`foo`');
        $delete->where('`bar`', '=', 'value');
        $delete->limit(2, 5);

        $this->assertEquals(
            'DELETE FROM' . PHP_EOL .
            '    `foo`' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    `bar` = ?' . PHP_EOL .
            'LIMIT 2 OFFSET 5' . PHP_EOL,
            $delete->getStatement($binding)
        );
        $this->assertEquals(['value'], $binding);
    }

    public function testGetStatementWithOrderAndLimit()
    {
        $delete = new Delete();
        $binding = [];
        $delete->from('`foo`');
        $delete->orderBy('`bar`', Order::ORDER_DESC);
        $delete->limit(2, 5);

        $this->assertEquals(
            'DELETE FROM' . PHP_EOL .
            '    `foo`' . PHP_EOL .
            'ORDER BY' . PHP_EOL .
            '    `bar` DESC' . PHP_EOL .
            'LIMIT 2 OFFSET 5' . PHP_EOL,
            $delete->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }
}
