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
use Hector\Schema\Schema;
use Hector\Schema\SchemaContainer;
use Hector\Schema\Table;
use Iterator;

class SchemaContainerTest extends AbstractTestCase
{
    public function testSerialization(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $schemaContainer2 = unserialize(serialize($schemaContainer));

        $this->assertEquals($schemaContainer, $schemaContainer2);
    }

    public function testCount(): void
    {
        $schemaContainer = $this->getSchemaContainer();

        $this->assertCount(1, $schemaContainer);
    }

    public function testGetIterator(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $iterator = $schemaContainer->getIterator();

        $this->assertInstanceOf(Iterator::class, $iterator);
        $this->assertCount(1, $iterator);
        $this->assertContainsOnlyInstancesOf(Schema::class, $iterator);
    }

    public function testGetSchemas(): void
    {
        $schemaContainer = $this->getSchemaContainer();

        $this->assertCount(1, $schemaContainer->getSchemas());
        $this->assertContainsOnlyInstancesOf(Schema::class, $schemaContainer->getSchemas());

        foreach ($schemaContainer->getSchemas() as $schema) {
            $this->assertSame($schemaContainer, $schema->getContainer());
        }
    }

    public function testGetSchema(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $schema = $schemaContainer->getSchema('sakila');

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testGetSchemaNonexistent(): void
    {
        $this->expectException(NotFoundException::class);

        $schemaContainer = $this->getSchemaContainer();
        $schemaContainer->getSchema('foo');
    }

    public function testHasTable(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $result = $schemaContainer->hasTable('customer', 'sakila');

        $this->assertTrue($result);
    }

    public function testHasTableWithoutSchemaName(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $result = $schemaContainer->hasTable('customer');

        $this->assertTrue($result);
    }

    public function testHasTableWithUnexistentTable(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $result = $schemaContainer->hasTable('foo');

        $this->assertFalse($result);
    }

    public function testGetTable(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $table = $schemaContainer->getTable('customer', 'sakila');

        $this->assertInstanceOf(Table::class, $table);
        $this->assertEquals('customer', $table->getName());
        $this->assertEquals('sakila', $table->getSchemaName());
    }

    public function testGetTable_byAlias(): void
    {
        $schemaContainer = new SchemaContainer([
            new Schema(connection: 'test', name: 'table1_name', charset: 'utf8mb4'),
            $expected = new Schema(connection: 'test', name: 'table2_name', charset: 'utf8mb4', alias: 'myAlias'),
            new Schema(connection: 'test', name: 'table3_name', charset: 'utf8mb4'),
        ]);

        $this->assertSame($expected, $schemaContainer->getSchema('myAlias'));
        $this->assertSame($expected, $schemaContainer->getSchema('table2_name'));
    }

    public function testGetTableWithoutSchemaName(): void
    {
        $schemaContainer = $this->getSchemaContainer();
        $table = $schemaContainer->getTable('customer');

        $this->assertInstanceOf(Table::class, $table);
        $this->assertEquals('customer', $table->getName());
        $this->assertEquals('sakila', $table->getSchemaName());
    }

    public function testGetTableWithBadSchemaName(): void
    {
        $this->expectException(NotFoundException::class);

        $schemaContainer = $this->getSchemaContainer();
        $schemaContainer->getTable('customer', 'foo');
    }

    public function testGetTableNonexistent(): void
    {
        $this->expectException(NotFoundException::class);

        $schemaContainer = $this->getSchemaContainer();
        $schemaContainer->getTable('foo');
    }
}
