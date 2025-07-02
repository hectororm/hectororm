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
use Hector\Schema\ForeignKey;

class ForeignKeyTest extends AbstractTestCase
{
    public function testSerialization()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];
        /** @var ForeignKey $foreignKey2 */
        $foreignKey2 = unserialize(serialize($foreignKey));

        $this->assertEquals($foreignKey->getName(), $foreignKey2->getName());
        $this->assertEquals($foreignKey->getColumnsName(), $foreignKey2->getColumnsName());
        $this->assertEquals($foreignKey->getReferencedSchemaName(), $foreignKey2->getReferencedSchemaName());
        $this->assertEquals($foreignKey->getReferencedTableName(), $foreignKey2->getReferencedTableName());
        $this->assertEquals($foreignKey->getReferencedColumnsName(), $foreignKey2->getReferencedColumnsName());
        $this->assertEquals($foreignKey->getUpdateRule(), $foreignKey2->getUpdateRule());
        $this->assertEquals($foreignKey->getDeleteRule(), $foreignKey2->getDeleteRule());
    }

    public function testGetName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];

        $this->assertEquals('fk_store_staff', $foreignKey->getName());
    }

    public function testGetColumnsName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];

        $this->assertEquals(['manager_staff_id'], $foreignKey->getColumnsName());
    }

    public function testGetReferencedSchemaName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];

        $this->assertEquals('sakila', $foreignKey->getReferencedSchemaName());
    }

    public function testGetReferencedTableName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];

        $this->assertEquals('staff', $foreignKey->getReferencedTableName());
    }

    public function testGetReferencedColumnsName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];

        $this->assertEquals(['staff_id'], $foreignKey->getReferencedColumnsName());
    }

    public function testGetUpdateRule()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];

        $this->assertEquals(ForeignKey::RULE_CASCADE, $foreignKey->getUpdateRule());
    }

    public function testGetDeleteRule()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];

        $this->assertEquals(ForeignKey::RULE_RESTRICT, $foreignKey->getDeleteRule());
    }

    public function testGetTable()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];

        $this->assertSame($table, $foreignKey->getTable());
    }

    public function testGetColumns()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[0];

        $this->assertContainsOnlyInstancesOf(Column::class, $foreignKey->getColumns());

        foreach ($foreignKey->getColumns() as $column) {
            $this->assertSame($table, $column->getTable());
        }
    }

    public function testGetReferencedTable()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];
        $tableReferenced = $this->getSchemaContainer()->getSchema('sakila')->getTable('staff');

        $this->assertSame($tableReferenced, $foreignKey->getReferencedTable());
    }

    public function testGetReferencedColumns()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $foreignKeys = iterator_to_array($table->getForeignKeys());
        $foreignKey = $foreignKeys[1];
        $tableReferenced = $this->getSchemaContainer()->getSchema('sakila')->getTable('staff');

        $this->assertContainsOnlyInstancesOf(Column::class, $foreignKey->getReferencedColumns());

        foreach ($foreignKey->getReferencedColumns() as $column) {
            $this->assertSame($tableReferenced, $column->getTable());
        }
    }
}
