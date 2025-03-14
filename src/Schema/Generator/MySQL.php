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

use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Index;
use Hector\Schema\Table;

/**
 * Class MySQL.
 */
class MySQL extends AbstractGenerator
{
    /**
     * @inheritDoc
     */
    protected function getSchemaInfo(string $name): array
    {
        $stm =
            'SELECT * ' .
            'FROM `information_schema`.`schemata` ' .
            'WHERE `schema_name` = :schema ' .
            ';';

        $result = $this->connection->fetchOne($stm, ['schema' => $name]);

        if (null === $result) {
            throw new SchemaException(sprintf('Schema "%s" not found', $name));
        }

        return [
            'name' => $result['SCHEMA_NAME'],
            'charset' => strtolower($result['DEFAULT_CHARACTER_SET_NAME']),
            'collation' => strtolower($result['DEFAULT_COLLATION_NAME']),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTablesInfo(string $name): array
    {
        $stm =
            'SELECT t.*, ' .
            '       c.`CHARACTER_SET_NAME` AS `TABLE_CHARACTER_SET_NAME` ' .
            'FROM `information_schema`.`tables` t ' .
            'LEFT JOIN `information_schema`.`collation_character_set_applicability` c ' .
            '    ON ( c.`' . match ($this->connection->getDriverInfo()->getDriver()) {
                'mariadb' => match (true) {
                    version_compare(
                        $this->connection->getDriverInfo()->getVersion(),
                        '11.5',
                        '>='
                    ) => 'full_collation_name',
                    default => 'collation_name',
                },
                default => 'collation_name'
            } . '` = t.`table_collation` ) ' .
            'WHERE t.`table_schema` = :schema ' .
            '  AND t.`table_type` IN (\'BASE TABLE\', \'VIEW\') ' .
            ';';

        $results = $this->connection->fetchAll($stm, ['schema' => $name]);

        $tablesInfo = [];
        foreach ($results as $result) {
            $tablesInfo[] = [
                'schema_name' => $result['TABLE_SCHEMA'],
                'name' => $result['TABLE_NAME'],
                'charset' =>
                    $result['TABLE_CHARACTER_SET_NAME'] ?
                        strtolower($result['TABLE_CHARACTER_SET_NAME']) :
                        null,
                'collation' => $result['TABLE_COLLATION'] ? strtolower($result['TABLE_COLLATION']) : null,
                'type' => $result['TABLE_TYPE'] === 'VIEW' ? Table::TYPE_VIEW : Table::TYPE_TABLE,
            ];
        }

        usort($tablesInfo, fn($table1, $table2) => strcmp($table1['name'], $table2['name']));

        return $tablesInfo;
    }

    /**
     * @inheritDoc
     */
    protected function getColumnsInfo(string $schema, string $table): array
    {
        $stm =
            'SELECT * ' .
            'FROM `information_schema`.`columns` ' .
            'WHERE `table_schema` = :schema ' .
            '  AND `table_name` = :table ' .
            'ORDER BY `ordinal_position` ASC ' .
            ';';

        $results = $this->connection->fetchAll($stm, ['schema' => $schema, 'table' => $table]);

        $columnsInfo = [];
        foreach ($results as $result) {
            $columnsInfo[] = [
                'name' => $result['COLUMN_NAME'],
                'position' => (int)$result['ORDINAL_POSITION'] - 1,
                'default' => $this->getDefaultValue($result['COLUMN_DEFAULT']),
                'nullable' => $result['IS_NULLABLE'] === 'YES',
                'type' => strtolower($result['DATA_TYPE']),
                'auto_increment' => false !== stripos($result['EXTRA'], 'auto_increment'),
                'maxlength' => $result['CHARACTER_MAXIMUM_LENGTH'] ? (int)$result['CHARACTER_MAXIMUM_LENGTH'] : null,
                'numeric_precision' => $result['NUMERIC_PRECISION'] ? (int)$result['NUMERIC_PRECISION'] : null,
                'numeric_scale' => $result['NUMERIC_SCALE'] ? (int)$result['NUMERIC_SCALE'] : null,
                'unsigned' => false !== stripos($result['COLUMN_TYPE'], 'unsigned'),
                'charset' => $result['CHARACTER_SET_NAME'] ? strtolower($result['CHARACTER_SET_NAME']) : null,
                'collation' => $result['COLLATION_NAME'] ? strtolower($result['COLLATION_NAME']) : null,
            ];
        }

        return $columnsInfo;
    }

    /**
     * Get default value.
     *
     * @param string|null $default
     *
     * @return string|null
     */
    protected function getDefaultValue(?string $default): ?string
    {
        // MariaDB?
        if ('mariadb' == $this->connection->getDriverInfo()->getDriver()) {
            // MariaDB NULL values
            if ("NULL" === $default) {
                return null;
            }

            // Empty
            if ("''" === $default) {
                return '';
            }

            // Timestamp
            if ("current_timestamp()" === $default) {
                return 'CURRENT_TIMESTAMP';
            }
        }

        return $default;
    }

    /**
     * @inheritDoc
     */
    protected function getIndexesInfo(string $schema, string $table): array
    {
        $stm =
            'SELECT * ' .
            'FROM `information_schema`.`statistics` ' .
            'WHERE `table_schema` = :schema ' .
            '  AND `table_name` = :table ' .
            'ORDER BY `index_name`, `seq_in_index` ASC ' .
            ';';

        $results = $this->connection->fetchAll($stm, ['schema' => $schema, 'table' => $table]);

        $indexesInfo = [];
        foreach ($results as $result) {
            $key = $result['TABLE_NAME'] . $result['INDEX_NAME'];

            if (!array_key_exists($key, $indexesInfo)) {
                $indexType = Index::INDEX;
                if ($result['NON_UNIQUE'] == 0) {
                    $indexType = Index::UNIQUE;
                    if ($result['INDEX_NAME'] === 'PRIMARY') {
                        $indexType = Index::PRIMARY;
                    }
                }
                $indexesInfo[$key] =
                    [
                        'table_name' => $result['TABLE_NAME'],
                        'name' => $result['INDEX_NAME'],
                        'type' => $indexType,
                        'columns_name' => [$result['COLUMN_NAME']]
                    ];
                continue;
            }

            $indexesInfo[$key]['columns_name'][] = $result['COLUMN_NAME'];
        }

        return array_values($indexesInfo);
    }

    /**
     * @inheritDoc
     */
    protected function getForeignKeysInfo(string $schema, string $table): array
    {
        $stm =
            'SELECT k.*, ' .
            '       c.`UPDATE_RULE`, ' .
            '       c.`DELETE_RULE` ' .
            'FROM `information_schema`.`key_column_usage` k, ' .
            '     `information_schema`.`referential_constraints` c ' .
            'WHERE k.`table_schema` = :schema ' .
            '  AND k.`table_name` = :table ' .
            '  AND k.`constraint_name` = c.`constraint_name` ' .
            'ORDER BY k.`ordinal_position` ASC ' .
            ';';

        $results = $this->connection->fetchAll($stm, ['schema' => $schema, 'table' => $table]);

        $foreignKeysInfo = [];
        foreach ($results as $result) {
            $key = $result['CONSTRAINT_NAME'];

            if (!array_key_exists($key, $foreignKeysInfo)) {
                $foreignKeysInfo[$key] =
                    [
                        'name' => $result['CONSTRAINT_NAME'],
                        'table_name' => $result['TABLE_NAME'],
                        'columns_name' => [$result['COLUMN_NAME']],
                        'referenced_schema_name' => $result['REFERENCED_TABLE_SCHEMA'],
                        'referenced_table_name' => $result['REFERENCED_TABLE_NAME'],
                        'referenced_columns_name' => [$result['REFERENCED_COLUMN_NAME']],
                        'update_rule' => $result['UPDATE_RULE'],
                        'delete_rule' => $result['DELETE_RULE'],
                    ];
                continue;
            }

            $foreignKeysInfo[$key]['columns_name'][] = $result['COLUMN_NAME'];
            $foreignKeysInfo[$key]['referenced_columns_name'][] = $result['REFERENCED_COLUMN_NAME'];
        }

        return array_values($foreignKeysInfo);
    }
}