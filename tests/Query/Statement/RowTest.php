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

namespace Hector\Query\Tests\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Statement\Encapsulated;
use Hector\Query\Statement\Quoted;
use Hector\Query\Statement\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    public function testGetStatement(): void
    {
        $row = new Row('foo', '`bar`', 'baz');
        $binds = new BindParamList();

        $this->assertEquals('( foo, `bar`, baz )', $row->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithEncapsulation(): void
    {
        $row = new Row('foo', '`bar`', 'baz');
        $binds = new BindParamList();

        $this->assertEquals('( ( foo, `bar`, baz ) )', (new Encapsulated($row))->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithQuotedValues(): void
    {
        $row = new Row(new Quoted('main.id'), new Quoted('main.name'));
        $binds = new BindParamList();

        $this->assertSame('( `main`.`id`, `main`.`name` )', $row->getStatement($binds));
    }

    public function testGetStatementWithQuotedAndPostgreSQL(): void
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('getIdentifierQuote')->willReturn('"');

        $row = new Row(new Quoted('main.id'), new Quoted('main.name'));
        $binds = new BindParamList();

        $this->assertSame('( "main"."id", "main"."name" )', $row->getStatement($binds, $capabilities));
    }

    public function testGetStatementMixedValues(): void
    {
        $row = new Row('raw_col', new Quoted('main.id'));
        $binds = new BindParamList();

        $this->assertSame('( raw_col, `main`.`id` )', $row->getStatement($binds));
    }
}
