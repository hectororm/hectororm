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

class MySQLTest extends TestCase
{
    private function getGenerator(): FakeMySQL
    {
        return new FakeMySQL(new Connection(getenv('MYSQL_DSN')));
    }

    public function testGetSchemaInfo(): void
    {
        $info = $this->getGenerator()->getSchemaInfo('sakila');

        $this->assertEquals('sakila', $info['name']);
        $this->assertEquals('utf8mb4', $info['charset']);
        $this->assertStringStartsWith('utf8mb4_', $info['collation']);
    }

    public function testGetTablesInfo(): void
    {
        $schemaInfo = $this->getGenerator()->getSchemaInfo('sakila');

        $this->assertEquals(
            [
                [
                    'name' => 'actor',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'actor_info',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'address',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'category',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'city',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'country',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'customer',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'customer_list',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'film',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_actor',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_category',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'film_list',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'film_text',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'inventory',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'language',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'nicer_but_slower_film_list',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'payment',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'rental',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'sales_by_film_category',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'sales_by_store',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'staff',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ],
                [
                    'name' => 'staff_list',
                    'schema_name' => 'sakila',
                    'charset' => null,
                    'collation' => null,
                    'type' => Table::TYPE_VIEW,
                ],
                [
                    'name' => 'store',
                    'schema_name' => 'sakila',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
                    'type' => Table::TYPE_TABLE,
                ]
            ],
            $this->getGenerator()->getTablesInfo('sakila')
        );
    }

    public function testGetColumnsInfo(): void
    {
        $schemaInfo = $this->getGenerator()->getSchemaInfo('sakila');

        $this->assertEquals(
            [
                [
                    'name' => 'address_id',
                    'charset' => null,
                    'collation' => null,
                    'position' => 0,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'smallint',
                    'auto_increment' => true,
                    'maxlength' => null,
                    'numeric_precision' => 5,
                    'numeric_scale' => 0,
                    'unsigned' => true,
                ],
                [
                    'name' => 'address',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
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
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
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
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
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
                    'numeric_precision' => 5,
                    'numeric_scale' => 0,
                    'unsigned' => true,
                ],
                [
                    'name' => 'postal_code',
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
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
                    'charset' => 'utf8mb4',
                    'collation' => $schemaInfo['collation'],
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
                    'name' => 'location',
                    'charset' => null,
                    'collation' => null,
                    'position' => 7,
                    'default' => null,
                    'nullable' => false,
                    'type' => 'geometry',
                    'auto_increment' => false,
                    'maxlength' => null,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
                [
                    'name' => 'last_update',
                    'charset' => null,
                    'collation' => null,
                    'position' => 8,
                    'default' => 'CURRENT_TIMESTAMP',
                    'nullable' => false,
                    'type' => 'timestamp',
                    'auto_increment' => false,
                    'maxlength' => null,
                    'numeric_precision' => null,
                    'numeric_scale' => null,
                    'unsigned' => false,
                ],
            ],
            $this->getGenerator()->getColumnsInfo('sakila', 'address')
        );
    }

    /**
     * A zero numeric scale (integers, DECIMAL(x,0)) must be reported as 0, not null:
     * NUMERIC_SCALE = 0 is falsy and used to be turned into null. Uses a strict comparison
     * because assertEquals() treats 0 and null as equal.
     */
    public function testGetColumnsInfoKeepsZeroNumericScale(): void
    {
        $columns = $this->getGenerator()->getColumnsInfo('sakila', 'address');
        $byName = array_column($columns, 'numeric_scale', 'name');

        // smallint columns have a scale of 0...
        $this->assertSame(0, $byName['address_id']);
        $this->assertSame(0, $byName['city_id']);
        // ...while a non-numeric column has no scale at all.
        $this->assertNull($byName['address']);
    }

    public function testGetIndexesInfo(): void
    {
        $this->assertEquals(
            [
                [
                    'name' => 'idx_fk_city_id',
                    'type' => 'index',
                    'table_name' => 'address',
                    'columns_name' => ['city_id'],
                ],
                [
                    'name' => 'idx_location',
                    'type' => 'index',
                    'table_name' => 'address',
                    'columns_name' => ['location']
                ],
                [
                    'name' => 'PRIMARY',
                    'type' => 'primary',
                    'table_name' => 'address',
                    'columns_name' => ['address_id'],
                ],
            ],
            $this->getGenerator()->getIndexesInfo('sakila', 'address')
        );
    }

    public function testGetForeignKeysInfo(): void
    {
        $this->assertEquals(
            [
                [
                    'name' => 'fk_address_city',
                    'table_name' => 'address',
                    'columns_name' => ['city_id'],
                    'referenced_schema_name' => 'sakila',
                    'referenced_table_name' => 'city',
                    'referenced_columns_name' => ['city_id'],
                    'update_rule' => 'CASCADE',
                    'delete_rule' => 'RESTRICT',
                ],
            ],
            $this->getGenerator()->getForeignKeysInfo('sakila', 'address')
        );
    }

    /**
     * Foreign key introspection must match the constraint schema in the join: constraint
     * names are unique only per schema, so another database holding a same-named foreign key
     * must not leak into the result (cartesian product duplicating columns / wrong rules).
     */
    public function testGetForeignKeysInfoIsScopedToSchema(): void
    {
        $connection = new Connection(getenv('MYSQL_DSN'));
        $database = 'hector_rob07_fk_scope';

        // Second database with a foreign key named exactly like Sakila's `fk_address_city`,
        // but with different referential rules.
        $connection->execute(sprintf('DROP DATABASE IF EXISTS `%s`;', $database));
        $connection->execute(sprintf('CREATE DATABASE `%s`;', $database));

        try {
            $connection->execute(sprintf('CREATE TABLE `%s`.`city` (`city_id` INT PRIMARY KEY);', $database));
            $connection->execute(
                sprintf(
                    'CREATE TABLE `%s`.`address` ('
                    . '`address_id` INT PRIMARY KEY, `city_id` INT, '
                    . 'CONSTRAINT `fk_address_city` FOREIGN KEY (`city_id`) '
                    . 'REFERENCES `%s`.`city` (`city_id`) ON UPDATE NO ACTION ON DELETE NO ACTION'
                    . ');',
                    $database,
                    $database
                )
            );

            $this->assertEquals(
                [
                    [
                        'name' => 'fk_address_city',
                        'table_name' => 'address',
                        'columns_name' => ['city_id'],
                        'referenced_schema_name' => 'sakila',
                        'referenced_table_name' => 'city',
                        'referenced_columns_name' => ['city_id'],
                        'update_rule' => 'CASCADE',
                        'delete_rule' => 'RESTRICT',
                    ],
                ],
                (new FakeMySQL($connection))->getForeignKeysInfo('sakila', 'address')
            );
        } finally {
            $connection->execute(sprintf('DROP DATABASE IF EXISTS `%s`;', $database));
        }
    }

    public function testGenerateSchema(): void
    {
        $generator = $this->getGenerator();
        $schemaContainer = $generator->generateSchemas('sakila');

        $this->assertInstanceOf(SchemaContainer::class, $schemaContainer);
        $this->assertCount(1, $schemaContainer);
        $this->assertInstanceOf(Schema::class, $schema = $schemaContainer->getSchema('sakila'));
        $this->assertEquals('sakila', $schema->getName());
        $this->assertCount(23, $schema);
    }
}
