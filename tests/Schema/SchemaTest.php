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

namespace Hector\Schema\Tests;

use Hector\Schema\Exception\NotFoundException;
use Hector\Schema\SchemaContainer;
use Hector\Schema\Table;

class SchemaTest extends AbstractTestCase
{
    public function testSerialization()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $schema2 = unserialize(serialize($schema));

        $this->assertEquals($schema->getName(), $schema2->getName());
        $this->assertEquals($schema->getCharset(), $schema2->getCharset());
        $this->assertEquals($schema->getCollation(), $schema2->getCollation());
        $this->assertCount(count(iterator_to_array($schema->getTables())), $schema2->getTables());
    }

    public function testCount()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertCount(23, $schema);
    }

    public function testGetIterator()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $iterator = $schema->getIterator();

        $this->assertInstanceOf(\Iterator::class, $iterator);
        $this->assertCount(23, $iterator);
        $this->assertContainsOnlyInstancesOf(Table::class, $iterator);
    }

    public function testGetName()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertEquals('sakila', $schema->getName());
        $this->assertEquals($schema->getName(), $schema->getName(false));
    }

    public function testGetNameQuoted()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertEquals('`sakila`', $schema->getName(true));
    }

    public function testGetCharset()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertEquals('utf8mb4', $schema->getCharset());
    }

    public function testGetCollation()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertStringStartsWith('utf8mb4_', $schema->getCollation());
    }

    public function testGetTables()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertCount(23, $schema->getTables());
        $this->assertContainsOnlyInstancesOf(Table::class, $schema->getTables());

        foreach ($schema->getTables() as $table) {
            $this->assertEquals('sakila', $table->getSchemaName());
            $this->assertSame($schema, $table->getSchema());
        }
    }

    public function testGetTablesWithType()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $tables = $schema->getTables(Table::TYPE_TABLE);
        $this->assertCount(16, $tables);

        $tables = $schema->getTables(Table::TYPE_VIEW);
        $this->assertCount(7, $tables);
    }

    public function testGetTable()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertInstanceOf(Table::class, $table);
        $this->assertEquals('customer', $table->getName());
        $this->assertEquals('sakila', $table->getSchemaName());
    }

    public function testGetTableNonexistent()
    {
        $this->expectException(NotFoundException::class);

        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $schema->getTable('foo');
    }

    public function testGetContainer()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');

        $this->assertInstanceOf(SchemaContainer::class, $schema->getContainer());
        $this->assertSame($this->getSchemaContainer(), $schema->getContainer());
    }
}
