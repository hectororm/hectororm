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

declare(strict_types=1);

namespace Hector\Schema\Generator;

use Hector\Connection\Connection;
use Hector\Schema\Column;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Schema;
use Hector\Schema\SchemaContainer;
use Hector\Schema\Table;

/**
 * Class AbstractGenerator.
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    public function __construct(protected Connection $connection)
    {
    }

    /**
     * Get schema info.
     *
     * @param string $name
     *
     * @return array
     * @throws SchemaException
     */
    abstract protected function getSchemaInfo(string $name): array;

    /**
     * Get tables info.
     *
     * @param string $name
     *
     * @return array[array]
     * @throws SchemaException
     */
    abstract protected function getTablesInfo(string $name): array;

    /**
     * Get columns info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array[array]
     * @throws SchemaException
     */
    abstract protected function getColumnsInfo(string $schema, string $table): array;

    /**
     * Get indexes info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array
     * @throws SchemaException
     */
    abstract protected function getIndexesInfo(string $schema, string $table): array;

    /**
     * Get foreign keys info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array
     * @throws SchemaException
     */
    abstract protected function getForeignKeysInfo(string $schema, string $table): array;

    /**
     * @inheritDoc
     */
    public function generateSchema(string $name): Schema
    {
        // Get schema info
        $schemaInfo = $this->getSchemaInfo($name);

        $tables = [];
        foreach ($this->getTablesInfo($name) as $tableInfo) {
            $tableName = $tableInfo['name'];

            $columns = [];
            foreach ($this->getColumnsInfo($name, $tableName) as $columnInfo) {
                $column = new Column(
                    name:              $columnInfo['name'],
                    position:          $columnInfo['position'],
                    default:           $columnInfo['default'],
                    nullable:          $columnInfo['nullable'],
                    type:              $columnInfo['type'],
                    auto_increment:    $columnInfo['auto_increment'] ?? false,
                    maxlength:         $columnInfo['maxlength'] ?? null,
                    numeric_precision: $columnInfo['numeric_precision'] ?? null,
                    numeric_scale:     $columnInfo['numeric_scale'] ?? null,
                    unsigned:          $columnInfo['unsigned'] ?? false,
                    charset:           $columnInfo['charset'] ?? null,
                    collation:         $columnInfo['collation'] ?? null,
                );
                $columns[$column->getName()] = $column;
            }
            uasort(
                $columns,
                fn(Column $column1, Column $column2) => $column1->getPosition() <=> $column2->getPosition()
            );

            $indexes = [];
            foreach ($this->getIndexesInfo($name, $tableName) as $indexInfo) {
                $index = new Index(
                    name:         $indexInfo['name'],
                    type:         $indexInfo['type'],
                    columns_name: $indexInfo['columns_name'] ?? [],
                );
                $indexes[$index->getName()] = $index;
            }
            ksort($indexes);

            $foreignKeys = [];
            foreach ($this->getForeignKeysInfo($name, $tableName) as $foreignKeyInfo) {
                $foreignKey = new ForeignKey(
                    name:                    $foreignKeyInfo['name'],
                    columns_name:            $foreignKeyInfo['columns_name'],
                    referenced_schema_name:  $foreignKeyInfo['referenced_schema_name'],
                    referenced_table_name:   $foreignKeyInfo['referenced_table_name'],
                    referenced_columns_name: $foreignKeyInfo['referenced_columns_name'],
                    update_rule:             $foreignKeyInfo['update_rule'] ?? ForeignKey::RULE_NO_ACTION,
                    delete_rule:             $foreignKeyInfo['delete_rule'] ?? ForeignKey::RULE_NO_ACTION,
                );
                $foreignKeys[$foreignKey->getName()] = $foreignKey;
            }
            ksort($foreignKeys);

            $tables[$tableName] = new Table(
                schema_name:  $tableInfo['schema_name'],
                type:         $tableInfo['type'],
                name:         $tableName,
                charset:      $tableInfo['charset'] ?? null,
                collation:    $tableInfo['collation'] ?? null,
                columns:      $columns ?? [],
                indexes:      $indexes ?? [],
                foreign_keys: $foreignKeys ?? [],
            );
            ksort($tables);
        }

        return new Schema(
            connection: $this->connection->getName(),
            name:       $schemaInfo['name'],
            charset:    $schemaInfo['charset'],
            collation:  $schemaInfo['collation'] ?? null,
            tables:     $tables,
        );
    }

    /**
     * @inheritDoc
     */
    public function generateSchemas(string ...$names): SchemaContainer
    {
        $schemas = [];

        try {
            foreach ($names as $name) {
                $schemas[] = $this->generateSchema($name);
            }

            $container = new SchemaContainer($schemas);
        } catch (SchemaException $e) {
            throw $e;
        }

        return $container;
    }
}