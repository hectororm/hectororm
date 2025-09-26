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
 * Class Sqlite.
 */
class Sqlite extends AbstractGenerator
{
    private ?string $encoding = null;

    /**
     * Get encoding.
     *
     * @return string
     */
    protected function getEncoding(): string
    {
        if (null !== $this->encoding) {
            return $this->encoding;
        }

        $encodingStm = 'PRAGMA encoding;';
        $encodingResult = $this->connection->fetchOne($encodingStm);

        $this->encoding = strtolower($encodingResult['encoding']);

        return $this->encoding;
    }

    /**
     * @inheritDoc
     */
    protected function getSchemaInfo(string $name): array
    {
        return [
            'name' => $name,
            'charset' => $this->getEncoding(),
            'collation' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getTablesInfo(string $name): array
    {
        $stm =
            'SELECT t.* ' .
            'FROM `sqlite_master` AS t ' .
            'WHERE t.`type` IN ( \'table\', \'view\' ) ' .
            '  AND t.`name` NOT LIKE \'sqlite_%\' ' .
            'ORDER BY t.`name` ' .
            ';';

        $results = $this->connection->fetchAll($stm);

        $tablesInfo = [];
        foreach ($results as $result) {
            $tablesInfo[] = [
                'schema_name' => 'main',
                'name' => $result['tbl_name'],
                'charset' => $this->getEncoding(),
                'collation' => null,
                'type' => $result['type'] === 'view' ? Table::TYPE_VIEW : Table::TYPE_TABLE,
            ];
        }

        return $tablesInfo;
    }

    /**
     * @inheritDoc
     */
    protected function getColumnsInfo(string $schema, string $table): array
    {
        $stm = 'PRAGMA table_info(\'' . $table . '\');';
        $results = $this->connection->fetchAll($stm);

        // Get sql statement
        $stm =
            'SELECT t.`sql` ' .
            'FROM `sqlite_master` AS t ' .
            'WHERE t.`type` IN ( \'table\', \'view\' ) ' .
            '  AND t.`name` = \'' . $table . '\' ' .
            ';';
        $tableStatement = $this->connection->fetchOne($stm);
        if (null === $tableStatement) {
            throw new SchemaException(sprintf('Table "%s" not found', $table));
        }
        $tableStatement = $tableStatement['sql'];

        $columnsInfo = [];
        foreach ($results as $result) {
            $type = $this->getTypeInfo($result['type']);
            $autoIncrement =
                preg_match(
                    sprintf('/[^,()]\s+%s\s+[^,()]*autoincrement/im', preg_quote($result['name'])),
                    $tableStatement
                ) === 1;

            $columnsInfo[] = [
                'name' => $result['name'],
                'position' => (int)$result['cid'],
                'default' =>
                    $result['dflt_value'] == 'NULL' ||
                    null === $result['dflt_value'] ?
                        null : $result['dflt_value'],
                'nullable' => $result['notnull'] == '0' && $result['pk'] != '1' && !$autoIncrement,
                'type' => $type['name'],
                'auto_increment' => $autoIncrement,
                'maxlength' => $type['maxlength'],
                'numeric_precision' => $type['numeric_precision'],
                'numeric_scale' => $type['numeric_scale'],
                'unsigned' => $type['unsigned'],
                'charset' => $type['is_string'] ? $this->getEncoding() : null,
                'collation' => null,
            ];
        }

        return $columnsInfo;
    }

    /**
     * Get type info.
     *
     * @param string $type
     *
     * @return array|null
     */
    private function getTypeInfo(string $type): ?array
    {
        $matches = [];
        if (preg_match('/(\w+)(\s+unsigned\s*)?(?:\((\d+)(?:,(\d+))?\))?$/i', $type, $matches) !== 1) {
            return [
                'name' => 'text',
                'is_string' => true,
                'maxlength' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'unsigned' => false,
            ];
        }

        $isString = preg_match('/(CHAR|CLOB|TEXT)/i', $type) === 1;

        switch (strtolower($matches[1])) {
            case 'integer':
                $typeName = 'int';
                break;
            default:
                $typeName = strtolower($matches[1]);
        }

        return [
            'name' => $typeName,
            'is_string' => $isString,
            'maxlength' => $isString && isset($matches[3]) ? (int)$matches[3] : null,
            'numeric_precision' => isset($matches[4]) ? (int)$matches[3] : null,
            'numeric_scale' => isset($matches[4]) ? (int)$matches[4] : null,
            'unsigned' => isset($matches[2]) && trim(strtolower($matches[2])) === 'unsigned',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getIndexesInfo(string $schema, string $table): array
    {
        $stm = 'PRAGMA index_list(\'' . $table . '\');';
        $results = $this->connection->fetchAll($stm);

        $indexesInfo = [];
        foreach ($results as $result) {
            $indexType = Index::INDEX;
            if ($result['unique'] == '1') {
                $indexType = Index::UNIQUE;
                if ($result['origin'] === 'pk') {
                    $indexType = Index::PRIMARY;
                }
            }

            // Get columns
            $indexInfoStm = 'PRAGMA index_info(\'' . $result['name'] . '\');';
            $resultsIndexInfo = $this->connection->fetchAll($indexInfoStm);

            $indexesInfo[] = [
                'table_name' => $table,
                'name' => $result['name'],
                'type' => $indexType,
                'columns_name' => array_column($resultsIndexInfo, 'name'),
            ];
        }

        return $indexesInfo;
    }

    /**
     * @inheritDoc
     */
    protected function getForeignKeysInfo(string $schema, string $table): array
    {
        $stm = 'PRAGMA foreign_key_list(\'' . $table . '\');';
        $results = $this->connection->fetchAll($stm);

        $foreignKeysInfo = [];
        foreach ($results as $result) {
            $key = (int)$result['id'];

            if (!array_key_exists($key, $foreignKeysInfo)) {
                $foreignKeysInfo[$key] =
                    [
                        'name' => (string)$key,
                        'table_name' => $table,
                        'columns_name' => [$result['from']],
                        'referenced_schema_name' => $schema,
                        'referenced_table_name' => $result['table'],
                        'referenced_columns_name' => [$result['to']],
                        'update_rule' => $result['on_update'],
                        'delete_rule' => $result['on_delete'],
                    ];
                continue;
            }

            $foreignKeysInfo[$key]['columns_name'][] = $result['from'];
            $foreignKeysInfo[$key]['referenced_columns_name'][] = $result['to'];
        }

        return array_values($foreignKeysInfo);
    }
}
