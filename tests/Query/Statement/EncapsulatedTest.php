<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Tests\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Statement\Encapsulated;
use Hector\Query\Statement\Raw;
use Hector\Query\Statement\Row;
use Hector\Query\Select;
use PHPUnit\Framework\TestCase;

class EncapsulatedTest extends TestCase
{
    public function testGetStatement(): void
    {
        $raw = new Raw('UNIX_TIMESTAMP()');
        $encapsulated = new Encapsulated($raw);
        $binds = new BindParamList();

        $this->assertEquals('( UNIX_TIMESTAMP() )', $encapsulated->getStatement($binds));
    }

    public function testGetStatementWithRow(): void
    {
        $row = new Row('foo', '`bar`', 'baz');
        $encapsulated = new Encapsulated($row);
        $binds = new BindParamList();

        $this->assertEquals('( ( foo, `bar`, baz ) )', $encapsulated->getStatement($binds));
    }

    public function testGetStatementWithSelect(): void
    {
        $select = new Select();
        $select->from('table');
        $encapsulated = new Encapsulated($select);
        $binds = new BindParamList();

        $this->assertEquals('( SELECT * FROM table )', $encapsulated->getStatement($binds));
    }

    public function testGetStatementReturnsNullForEmptyStatement(): void
    {
        $select = new Select();
        $encapsulated = new Encapsulated($select);
        $binds = new BindParamList();

        $this->assertNull($encapsulated->getStatement($binds));
    }

    public function testGetStatementWithBindParams(): void
    {
        $raw = new Raw('column = ?', ['value']);
        $encapsulated = new Encapsulated($raw);
        $binds = new BindParamList();

        $this->assertEquals('( column = ? )', $encapsulated->getStatement($binds));
        $this->assertCount(1, $binds);
    }

    public function testNestedEncapsulation(): void
    {
        $raw = new Raw('foo');
        $encapsulated = new Encapsulated(new Encapsulated($raw));
        $binds = new BindParamList();

        $this->assertEquals('( ( foo ) )', $encapsulated->getStatement($binds));
    }
}
