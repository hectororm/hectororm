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

namespace Hector\Schema\Tests\Generator;

use Hector\Connection\Connection;
use Hector\Schema\Schema;
use Hector\Schema\SchemaContainer;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class SqliteTest extends TestCase
{
    private function getGenerator(): FakeSqlite
    {
        $path = realpath(__DIR__ . '/sakila.db');

        return new FakeSqlite(new Connection('sqlite:' . $path));
    }

    public function testGetSchemaInfo()
    {
        $this->assertEquals(
            [
                'name' => 'main',
                'charset' => 'utf-8',
                'collation' => null,
            ],
            $this->getGenerator()->getSchemaInfo('main')
        );
    }

    public function testGetTablesInfo()
    {
        $this->assertEquals(
            [
                [
                    'name' => 'actor',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'actor_info',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'address',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'category',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'city',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'country',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'customer',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'customer_list',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'film',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_actor',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_category',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_list',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'film_text',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'inventory',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'language',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'nicer_but_slower_film_list',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'payment',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'rental',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'sales_by_film_category',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'sales_by_store',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'staff',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'staff_list',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'store',
                    'schema_name' => 'main',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'type' => Table::TYPE_TABLE,
                ],
            ],
            $this->getGenerator()->getTablesInfo('main')
        );
    }

    public function testGetColumnsInfo()
    {
        $this->assertEquals(
            [
                [
                    'name' => 'address_id',
                    'charset' => null,
                    'collation' => null,
                    'position' => 0,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'int',
                    'auto_increment' => true,
                    'maxlength' => null,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'address',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'position' => 1,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'varchar',
                    'auto_increment' => false,
                    'maxlength' => 50,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'address2',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'position' => 2,
                    'default' => null,
                    'nullable' => true,
                    'type' => 'varchar',
                    'auto_increment' => false,
                    'maxlength' => 50,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'district',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'position' => 3,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'varchar',
                    'auto_increment' => false,
                    'maxlength' => 20,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'city_id',
                    'charset' => null,
                    'collation' => null,
                    'position' => 4,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'smallint',
                    'auto_increment' => false,
                    'maxlength' => null,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => true,
                ],
                [
                    'name' => 'postal_code',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'position' => 5,
                    'default' => null,
                    'nullable' => true,
                    'type' => 'varchar',
                    'auto_increment' => false,
                    'maxlength' => 10,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'phone',
                    'charset' => 'utf-8',
                    'collation' => null,
                    'position' => 6,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'varchar',
                    'auto_increment' => false,
                    'maxlength' => 20,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'last_update',
                    'charset' => null,
                    'collation' => null,
                    'position' => 7,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'timestamp',
                    'auto_increment' => false,
                    'maxlength' => null,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
            ],
            $this->getGenerator()->getColumnsInfo('main', 'address')
        );
    }

    public function testGetIndexesInfo()
    {
        $this->assertEquals(
            [
                [
                    'name' => 'idx_fk_city_id',
                    'type' => 'index',
                    'table_name' => 'address',
                    'columns_name' => ['city_id'],
                ],
            ],
            $this->getGenerator()->getIndexesInfo('main', 'address')
        );
    }

    public function testGetForeignKeysInfo()
    {
        $this->assertEquals(
            [
                [
                    'name' => 0,
                    'table_name' => 'address',
                    'columns_name' => ['city_id'],
                    'referenced_schema_name' => 'main',
                    'referenced_table_name' => 'city',
                    'referenced_columns_name' => ['city_id'],
                    'update_rule' => 'CASCADE',
                    'delete_rule' => 'RESTRICT',
                ],
            ],
            $this->getGenerator()->getForeignKeysInfo('main', 'address')
        );
    }

    public function testGenerateSchema()
    {
        $generator = $this->getGenerator();
        $schemaContainer = $generator->generateSchemas('main');

        $this->assertInstanceOf(SchemaContainer::class, $schemaContainer);
        $this->assertCount(1, $schemaContainer);
        $this->assertInstanceOf(Schema::class, $schema = $schemaContainer->getSchema('main'));
        $this->assertEquals('main', $schema->getName());
        $this->assertCount(23, $schema);
    }
}
