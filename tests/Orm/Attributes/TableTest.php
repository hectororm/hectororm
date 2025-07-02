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

namespace Hector\Orm\Tests\Attributes;

use Hector\Connection\Connection;
use Hector\Orm\Attributes\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new Table('my_table', 'my_schema', 'my_connection');

        $this->assertEquals('my_table', $attribute->table);
        $this->assertEquals('my_schema', $attribute->schema);
        $this->assertEquals('my_connection', $attribute->connection);
    }

    public function testConstructWithoutConnection()
    {
        $attribute = new Table('my_table', 'my_schema');

        $this->assertEquals('my_table', $attribute->table);
        $this->assertEquals('my_schema', $attribute->schema);
        $this->assertEquals(Connection::DEFAULT_NAME, $attribute->connection);
    }

    public function testConstructWithoutSchema()
    {
        $attribute = new Table('my_table');

        $this->assertEquals('my_table', $attribute->table);
        $this->assertNull($attribute->schema);
        $this->assertEquals(Connection::DEFAULT_NAME, $attribute->connection);
    }
}
