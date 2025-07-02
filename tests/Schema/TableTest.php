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

use Hector\Schema\Column;
use Hector\Schema\Exception\NotFoundException;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Schema;

class TableTest extends AbstractTestCase
{
    public function testSerialization()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $table2 = unserialize(serialize($table));

        $this->assertEquals($table->getName(), $table2->getName());
        $this->assertEquals($table->getCharset(), $table2->getCharset());
        $this->assertEquals($table->getCollation(), $table2->getCollation());
        $this->assertCount(count(iterator_to_array($table->getColumns())), $table2->getColumns());
        $this->assertCount(count(iterator_to_array($table->getIndexes())), $table2->getIndexes());
    }

    public function testCount()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertCount(9, $table);
    }

    public function testGetIterator()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $iterator = $table->getIterator();

        $this->assertInstanceOf(\Iterator::class, $iterator);
        $this->assertCount(9, $iterator);
        $this->assertContainsOnlyInstancesOf(Column::class, $iterator);
    }

    public function testGetSchemaName()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('sakila', $table->getSchemaName());
        $this->assertEquals($table->getSchemaName(), $table->getSchemaName(false));
    }

    public function testGetSchemaNameQuoted()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('`sakila`', $table->getSchemaName(true));
    }

    public function testGetName()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('customer', $table->getName());
        $this->assertEquals($table->getName(), $table->getName(false));
    }

    public function testGetNameQuoted()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('`customer`', $table->getName(true));
    }

    public function testGetFullName()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('sakila.customer', $table->getFullName());
        $this->assertEquals($table->getFullName(), $table->getFullName(false));
    }

    public function testGetFullNameQuoted()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('`sakila`.`customer`', $table->getFullName(true));
    }

    public function testGetCharset()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals('utf8mb4', $table->getCharset());
    }

    public function testGetCollation()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertEquals($schema->getCollation(), $table->getCollation());
    }

    public function testGetColumns()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertCount(9, $table->getColumns());
        $this->assertContainsOnlyInstancesOf(Column::class, $table->getColumns());

        foreach ($table->getColumns() as $column) {
            $this->assertSame($table, $column->getTable());
        }
    }

    public function testGetColumnsName()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $columnsName = $table->getColumnsName();

        $this->assertEquals(
            [
                'customer_id',
                'store_id',
                'first_name',
                'last_name',
                'email',
                'address_id',
                'active',
                'create_date',
                'last_update'
            ],
            $columnsName
        );
    }

    public function testGetColumnsNameQuoted()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $columnsName = $table->getColumnsName(true);

        $this->assertEquals(
            [
                '`customer_id`',
                '`store_id`',
                '`first_name`',
                '`last_name`',
                '`email`',
                '`address_id`',
                '`active`',
                '`create_date`',
                '`last_update`'
            ],
            $columnsName
        );
    }

    public function testGetColumnsNameWithTableAlias()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $columnsName = $table->getColumnsName(false, 'alias');

        $this->assertEquals(
            [
                'alias.customer_id',
                'alias.store_id',
                'alias.first_name',
                'alias.last_name',
                'alias.email',
                'alias.address_id',
                'alias.active',
                'alias.create_date',
                'alias.last_update'
            ],
            $columnsName
        );
    }

    public function testGetColumnsNameQuotedWithTableAlias()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $columnsName = $table->getColumnsName(true, 'alias');

        $this->assertEquals(
            [
                '`alias`.`customer_id`',
                '`alias`.`store_id`',
                '`alias`.`first_name`',
                '`alias`.`last_name`',
                '`alias`.`email`',
                '`alias`.`address_id`',
                '`alias`.`active`',
                '`alias`.`create_date`',
                '`alias`.`last_update`'
            ],
            $columnsName
        );
    }

    public function testHasColumn()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertTrue($table->hasColumn('email'));
    }

    public function testHasColumnNonExistent()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertFalse($table->hasColumn('foo'));
    }

    public function testGetColumn()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $column = $table->getColumn('email');

        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals('email', $column->getName());
        $this->assertSame($table, $column->getTable());
    }

    public function testGetColumnNonexistent()
    {
        $this->expectException(NotFoundException::class);

        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $table->getColumn('foo');
    }

    public function testGetAutoIncrementColumn()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $column = $table->getAutoIncrementColumn();

        $this->assertInstanceOf(Column::class, $column);
        $this->assertTrue($column->isAutoIncrement());
        $this->assertSame($table, $column->getTable());
    }

    public function testGetAutoIncrementColumnOnNonAutoIncrementTable()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('film_category');
        $column = $table->getAutoIncrementColumn();

        $this->assertNull($column);
    }

    public function testGetIndexes()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertCount(4, $table->getIndexes());
        $this->assertContainsOnlyInstancesOf(Index::class, $table->getIndexes());

        foreach ($table->getIndexes() as $index) {
            $this->assertSame($table, $index->getTable());
        }
    }

    public function testGetIndex()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $index = $table->getIndex('idx_last_name');

        $this->assertInstanceOf(Index::class, $index);
        $this->assertSame($table, $index->getTable());
    }

    public function testGetIndexNonexistent()
    {
        $this->expectException(NotFoundException::class);

        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $table->getIndex('fake');
    }

    public function testGetForeignKeys()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertCount(2, $table->getForeignKeys());
        $this->assertContainsOnlyInstancesOf(ForeignKey::class, $table->getForeignKeys());

        foreach ($table->getForeignKeys() as $foreignKey) {
            $this->assertSame($table, $foreignKey->getTable());
        }
    }

    public function testGetForeignKeysWithTable()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $table2 = $schema->getTable('address');

        $this->assertCount(1, $table->getForeignKeys($table2));
        $this->assertContainsOnlyInstancesOf(ForeignKey::class, $table->getForeignKeys($table2));

        foreach ($table->getForeignKeys($table2) as $foreignKey) {
            $this->assertSame($table2, $foreignKey->getReferencedTable());
        }
    }

    public function testGetForeignKeysWithBadTable()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $table2 = $schema->getTable('actor');

        $this->assertCount(0, $table->getForeignKeys($table2));
    }

    public function testGetSchema()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');

        $this->assertInstanceOf(Schema::class, $table->getSchema());
        $this->assertSame($schema, $table->getSchema());
    }

    public function testGetPrimaryIndex()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $index = $table->getPrimaryIndex();

        $this->assertInstanceOf(Index::class, $index);
        $this->assertEquals(Index::PRIMARY, $index->getType());
        $this->assertSame($table, $index->getTable());
    }
}
