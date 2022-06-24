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
use Hector\Query\Statement\Row;
use PHPUnit\Framework\TestCase;

class RowTest extends TestCase
{
    public function testGetStatement()
    {
        $row = new Row('foo', '`bar`', 'baz');
        $binds = new BindParamList();

        $this->assertEquals('foo, `bar`, baz', $row->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithEncapsulation()
    {
        $row = new Row('foo', '`bar`', 'baz');
        $binds = new BindParamList();

        $this->assertEquals('(foo, `bar`, baz)', $row->getStatement($binds, true));
        $this->assertEmpty($binds);
    }
}
