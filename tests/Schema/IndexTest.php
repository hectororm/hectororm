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
use Hector\Schema\Index;

class IndexTest extends AbstractTestCase
{
    public function testSerialization()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getIndex('idx_unique_manager');
        $index2 = unserialize(serialize($index));

        $this->assertEquals($index->getName(), $index2->getName());
        $this->assertEquals($index->getType(), $index2->getType());
        $this->assertEquals($index->getColumnsName(), $index2->getColumnsName());
    }

    public function testGetName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getIndex('idx_unique_manager');

        $this->assertEquals('idx_unique_manager', $index->getName());
    }

    public function testGetNameOnPrimary()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertEquals('PRIMARY', $index->getName());
    }

    public function testGetTypeWithPrimary()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertEquals(Index::PRIMARY, $index->getType());
    }

    public function testGetTypeWithUnique()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getIndex('idx_unique_manager');

        $this->assertEquals(Index::UNIQUE, $index->getType());
    }

    public function testGetTypeWithStandard()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getIndex('idx_fk_address_id');

        $this->assertEquals(Index::INDEX, $index->getType());
    }

    public function testGetColumnsName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertEquals(['store_id'], $index->getColumnsName());
    }

    public function testGetTable()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertSame($table, $index->getTable());
    }

    public function testGetColumns()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertContainsOnlyInstancesOf(Column::class, $index->getColumns());

        foreach ($index->getColumns() as $column) {
            $this->assertSame($table, $column->getTable());
        }
    }

    public function testHasColumn()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('store');
        $index = $table->getPrimaryIndex();

        $this->assertTrue($index->hasColumn($table->getColumn('store_id')));
        $this->assertFalse($index->hasColumn($table->getColumn('manager_staff_id')));
    }
}
