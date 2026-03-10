<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema\Plan\Compiler;

use Hector\Schema\Index;
use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\AlterView;
use Hector\Schema\Plan\Operation\CreateTable;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\OperationInterface;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TablePlan;
use Hector\Schema\Schema;

class SqliteCompiler extends AbstractCompiler
{
    /**
     * @inheritDoc
     */
    protected function getIdentifierQuote(): string
    {
        return '"';
    }

    /**
     * @inheritDoc
     */
    protected function compileCreateTable(ObjectPlan $objectPlan): array
    {
        $operations = $objectPlan->getArrayCopy();
        $createTable = $operations[0];
        assert($createTable instanceof CreateTable);

        $tableName = $this->quoteIdentifier($createTable->getObjectName());
        $remaining = array_slice($operations, 1);

        // Column definitions
        $definitions = array_map(
            fn(AddColumn $op) => $this->compileColumnDefinition($op),
            array_filter($remaining, fn($op) => $op instanceof AddColumn),
        );

        // Check if any column has autoIncrement (already has inline PRIMARY KEY)
        $addColumns = array_filter($remaining, fn($op) => $op instanceof AddColumn);
        $hasAutoIncrement = (bool)array_filter($addColumns, fn(AddColumn $op) => true === $op->isAutoIncrement());

        // Primary key inline (skip if autoIncrement handles it)
        if (false === $hasAutoIncrement) {
            array_push($definitions, ...array_map(
                fn(AddIndex $op) => sprintf('PRIMARY KEY (%s)', $this->quoteIdentifiers($op->getColumns())),
                array_filter($remaining, fn($op) => $op instanceof AddIndex && Index::PRIMARY === $op->getType()),
            ));
        }

        // Foreign keys inline
        array_push($definitions, ...array_map(
            fn(AddForeignKey $op) => $this->compileForeignKeyDefinition($op),
            array_filter($remaining, fn($op) => $op instanceof AddForeignKey),
        ));

        $statements = [];

        if (false === empty($definitions)) {
            $statements[] = sprintf(
                "CREATE TABLE %s%s (\n  %s\n)",
                $createTable->ifNotExists() ? 'IF NOT EXISTS ' : '',
                $tableName,
                implode(",\n  ", $definitions),
            );
        }

        // Non-primary indexes must be created separately in SQLite
        array_push($statements, ...array_map(
            fn(AddIndex $op) => $this->compileCreateIndex($createTable->getObjectName(), $op),
            array_filter($remaining, fn($op) => $op instanceof AddIndex && Index::PRIMARY !== $op->getType()),
        ));

        return $statements;
    }

    /**
     * @inheritDoc
     */
    protected function compileRenameTable(RenameTable $operation): string
    {
        return sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteIdentifier($operation->getObjectName()),
            $this->quoteIdentifier($operation->getNewName()),
        );
    }

    /**
     * @inheritDoc
     */
    protected function compileAlterTable(ObjectPlan $objectPlan, ?Schema $schema = null): array
    {
        if (null !== $schema && $this->needsRebuild($objectPlan)) {
            return $this->compileTableRebuild($objectPlan, $schema);
        }

        $statements = [];

        foreach ($objectPlan as $operation) {
            $compiled = $this->compileAlterOperation($objectPlan->getName(), $operation, $schema);
            array_push($statements, ...$compiled);
        }

        return $statements;
    }

    /**
     * Check if a table plan requires a table rebuild.
     *
     * @param ObjectPlan $objectPlan
     *
     * @return bool
     */
    protected function needsRebuild(ObjectPlan $objectPlan): bool
    {
        foreach ($objectPlan as $operation) {
            if (
                $operation instanceof ModifyColumn ||
                $operation instanceof DropColumn ||
                $operation instanceof AddForeignKey ||
                $operation instanceof DropForeignKey
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compile a table rebuild sequence.
     *
     * Uses the provided schema to introspect the current table, applies the operations
     * to produce a new schema, then generates the SQLite rebuild sequence:
     * 1. PRAGMA foreign_keys = OFF
     * 2. CREATE TABLE temp (new schema)
     * 3. INSERT INTO temp (...) SELECT ... FROM original
     * 4. DROP TABLE original
     * 5. ALTER TABLE temp RENAME TO original
     * 6. Recreate non-primary indexes
     * 7. PRAGMA foreign_keys = ON
     *
     * @param ObjectPlan $objectPlan
     * @param Schema $schema
     *
     * @return string[]
     */
    protected function compileTableRebuild(ObjectPlan $objectPlan, Schema $schema): array
    {
        $tableName = $objectPlan->getName();
        $tempName = sprintf('__htemp_%s_%s', substr(bin2hex(random_bytes(2)), 0, 3), $tableName);

        // Introspect current table from schema
        $table = $schema->getTable($tableName);

        // Build new column list, index list, FK list from current schema
        $columns = array_combine(
            array_map(fn($col) => $col->getName(), iterator_to_array($table->getColumns())),
            array_map(fn($col) => [
                'name' => $col->getName(),
                'type' => $col->getType() . ($col->getMaxlength() ? '(' . $col->getMaxlength() . ')' : ''),
                'nullable' => $col->isNullable(),
                'default' => $col->getDefault(),
                'hasDefault' => null !== $col->getDefault(),
                'autoIncrement' => $col->isAutoIncrement(),
            ], iterator_to_array($table->getColumns())),
        );

        $indexes = array_combine(
            array_map(fn($idx) => $idx->getName(), iterator_to_array($table->getIndexes())),
            array_map(fn($idx) => [
                'name' => $idx->getName(),
                'columns' => $idx->getColumnsName(),
                'type' => $idx->getType(),
            ], iterator_to_array($table->getIndexes())),
        );

        $foreignKeys = array_combine(
            array_map(fn($fk) => $fk->getName(), iterator_to_array($table->getForeignKeys())),
            array_map(fn($fk) => [
                'name' => $fk->getName(),
                'columns' => $fk->getColumnsName(),
                'referencedTable' => $fk->getReferencedTableName(),
                'referencedColumns' => $fk->getReferencedColumnsName(),
                'onUpdate' => $fk->getUpdateRule(),
                'onDelete' => $fk->getDeleteRule(),
            ], iterator_to_array($table->getForeignKeys())),
        );

        // Column mapping: old name => new name (for renames)
        $columnMapping = [];

        // Apply operations
        foreach ($objectPlan as $operation) {
            match ($operation::class) {
                AddColumn::class => $columns[$operation->getName()] = [
                    'name' => $operation->getName(),
                    'type' => $operation->getType(),
                    'nullable' => $operation->isNullable(),
                    'default' => $operation->getDefault(),
                    'hasDefault' => $operation->hasDefault(),
                    'autoIncrement' => $operation->isAutoIncrement(),
                ],
                DropColumn::class => (function () use (&$columns, $operation) {
                    unset($columns[$operation->getName()]);
                })(),
                ModifyColumn::class => isset($columns[$operation->getName()])
                    ? $columns[$operation->getName()] = [
                        'name' => $operation->getName(),
                        'type' => $operation->getType(),
                        'nullable' => $operation->isNullable(),
                        'default' => $operation->getDefault(),
                        'hasDefault' => $operation->hasDefault(),
                        'autoIncrement' => $operation->isAutoIncrement(),
                    ]
                    : null,
                RenameColumn::class => (function () use (&$columns, &$columnMapping, $operation) {
                    if (false === isset($columns[$operation->getName()])) {
                        return;
                    }

                    $col = $columns[$operation->getName()];
                    $col['name'] = $operation->getNewName();
                    unset($columns[$operation->getName()]);
                    $columns[$operation->getNewName()] = $col;
                    $columnMapping[$operation->getName()] = $operation->getNewName();
                })(),
                AddIndex::class => $indexes[$operation->getName()] = [
                    'name' => $operation->getName(),
                    'columns' => $operation->getColumns(),
                    'type' => $operation->getType(),
                ],
                DropIndex::class => (function () use (&$indexes, $operation) {
                    unset($indexes[$operation->getName()]);
                })(),
                AddForeignKey::class => $foreignKeys[$operation->getName()] = [
                    'name' => $operation->getName(),
                    'columns' => $operation->getColumns(),
                    'referencedTable' => $operation->getReferencedTable(),
                    'referencedColumns' => $operation->getReferencedColumns(),
                    'onUpdate' => $operation->getOnUpdate(),
                    'onDelete' => $operation->getOnDelete(),
                ],
                DropForeignKey::class => (function () use (&$foreignKeys, $operation) {
                    unset($foreignKeys[$operation->getName()]);
                })(),
                default => null,
            };
        }

        // Build the CREATE TABLE plan for the temp table
        // Only include PRIMARY indexes inline — non-primary indexes must be created
        // AFTER the original table is dropped, because SQLite index names are global
        // and would conflict with existing indexes on the original table.
        $primaryIndexes = array_filter($indexes, fn(array $idx) => Index::PRIMARY === $idx['type']);

        $rebuildPlan = new Plan();
        $rebuildPlan->create($tempName, function (TablePlan $t) use ($columns, $primaryIndexes, $foreignKeys) {
            array_walk($columns, fn(array $col) => $t->addColumn(
                name: $col['name'],
                type: $col['type'],
                nullable: $col['nullable'],
                default: $col['default'],
                hasDefault: $col['hasDefault'],
                autoIncrement: $col['autoIncrement'],
            ));

            array_walk($primaryIndexes, fn(array $idx) => $t->addIndex(
                name: $idx['name'],
                columns: $idx['columns'],
                type: $idx['type'],
            ));

            array_walk($foreignKeys, fn(array $fk) => $t->addForeignKey(
                name: $fk['name'],
                columns: $fk['columns'],
                referencedTable: $fk['referencedTable'],
                referencedColumns: $fk['referencedColumns'],
                onUpdate: $fk['onUpdate'],
                onDelete: $fk['onDelete'],
            ));
        });

        // Build migrate mapping: source columns -> target columns
        // Only columns that exist in both old and new table (by original name or rename)
        $migrateMapping = [];
        $originalColumnNames = array_map(fn($col) => $col->getName(), iterator_to_array($table->getColumns()));

        foreach ($originalColumnNames as $oldName) {
            // Column was renamed
            if (isset($columnMapping[$oldName])) {
                $newName = $columnMapping[$oldName];

                if (false === isset($columns[$newName])) {
                    continue;
                }

                $migrateMapping[$oldName] = $newName;
                continue;
            }

            // Column still exists (possibly modified)
            if (false === isset($columns[$oldName])) {
                continue;
            }

            $migrateMapping[$oldName] = $oldName;
        }

        $rebuildPlan->migrate($tableName, $tempName, $migrateMapping);
        $rebuildPlan->drop($tableName);
        $rebuildPlan->rename($tempName, $tableName);

        // Compile the rebuild plan
        $statements = [];
        $statements[] = 'PRAGMA foreign_keys = OFF';
        array_push($statements, ...iterator_to_array($rebuildPlan->getStatements($this), false));

        // Recreate non-primary indexes on the renamed table.
        // These must be created AFTER the DROP TABLE (which frees the index names)
        // and the RENAME (which gives the temp table its final name), because
        // SQLite index names are global to the database.
        foreach ($indexes as $idx) {
            if (Index::PRIMARY === $idx['type']) {
                continue;
            }

            $statements[] = $this->compileCreateIndex($tableName, new AddIndex(
                table: $tableName,
                name: $idx['name'],
                columns: $idx['columns'],
                type: $idx['type'],
            ));
        }

        $statements[] = 'PRAGMA foreign_keys = ON';

        return $statements;
    }

    /**
     * Compile an individual ALTER operation for SQLite.
     *
     * Without a schema, all operations generate SQL optimistically
     * (even if SQLite may not support them natively).
     *
     * @param string $tableName
     * @param OperationInterface $operation
     * @param Schema|null $schema
     *
     * @return string[]
     */
    protected function compileAlterOperation(
        string $tableName,
        OperationInterface $operation,
        ?Schema $schema = null
    ): array {
        $quotedTable = $this->quoteIdentifier($tableName);

        return match ($operation::class) {
            AddColumn::class => [
                sprintf(
                    'ALTER TABLE %s ADD COLUMN %s',
                    $quotedTable,
                    $this->compileColumnDefinition($operation),
                ),
            ],
            RenameColumn::class => [
                sprintf(
                    'ALTER TABLE %s RENAME COLUMN %s TO %s',
                    $quotedTable,
                    $this->quoteIdentifier($operation->getName()),
                    $this->quoteIdentifier($operation->getNewName()),
                ),
            ],
            DropColumn::class => [
                sprintf(
                    'ALTER TABLE %s DROP COLUMN %s',
                    $quotedTable,
                    $this->quoteIdentifier($operation->getName()),
                ),
            ],
            ModifyColumn::class => [
                sprintf(
                    'ALTER TABLE %s MODIFY COLUMN %s',
                    $quotedTable,
                    $this->compileColumnDefinition($operation),
                ),
            ],
            AddIndex::class => $this->compileAlterAddIndex($operation, $tableName, $schema),
            DropIndex::class => [
                sprintf('DROP INDEX IF EXISTS %s', $this->quoteIdentifier($operation->getName())),
            ],
            AddForeignKey::class => [
                sprintf(
                    'ALTER TABLE %s ADD %s',
                    $quotedTable,
                    $this->compileForeignKeyDefinition($operation),
                ),
            ],
            DropForeignKey::class => [
                sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $quotedTable,
                    $this->quoteIdentifier($operation->getName()),
                ),
            ],
            default => [],
        };
    }

    /**
     * Compile an AddIndex for ALTER TABLE in SQLite.
     *
     * When a schema is available, checks if the index already exists and emits
     * a DROP INDEX IF EXISTS + CREATE INDEX pair. Otherwise, emits only CREATE INDEX.
     *
     * @param AddIndex $operation
     * @param string $tableName
     * @param Schema|null $schema
     *
     * @return string[]
     */
    protected function compileAlterAddIndex(AddIndex $operation, string $tableName, ?Schema $schema): array
    {
        $indexExists = false;

        if (null !== $schema && $schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);

            foreach ($table->getIndexes() as $index) {
                if ($index->getName() === $operation->getName()) {
                    $indexExists = true;
                    break;
                }
            }
        }

        if (true === $indexExists) {
            return [
                sprintf('DROP INDEX IF EXISTS %s', $this->quoteIdentifier($operation->getName())),
                $this->compileCreateIndex($tableName, $operation),
            ];
        }

        return [$this->compileCreateIndex($tableName, $operation)];
    }

    /**
     * @inheritDoc
     */
    protected function compileColumnDefinition(AddColumn|ModifyColumn $operation): string
    {
        $parts = [
            $this->quoteIdentifier($operation->getName()),
            $operation->getType(),
        ];

        if (false === $operation->isNullable()) {
            $parts[] = 'NOT NULL';
        }

        $default = $this->compileDefault($operation);
        if ('' !== $default) {
            $parts[] = trim($default);
        }

        if (true === $operation->isAutoIncrement()) {
            $parts[] = 'PRIMARY KEY AUTOINCREMENT';
        }

        return implode(' ', $parts);
    }

    /**
     * Compile a CREATE INDEX statement.
     *
     * @param string $tableName
     * @param AddIndex $operation
     *
     * @return string
     */
    protected function compileCreateIndex(string $tableName, AddIndex $operation): string
    {
        $unique = Index::UNIQUE === $operation->getType() ? 'UNIQUE ' : '';

        return sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique,
            $this->quoteIdentifier($operation->getName()),
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifiers($operation->getColumns()),
        );
    }

    /**
     * @inheritDoc
     */
    protected function compileCreateView(CreateView $operation): array
    {
        $viewName = $this->quoteIdentifier($operation->getObjectName());
        $statements = [];

        // SQLite does not support OR REPLACE for views, so DROP first
        if (true === $operation->orReplace()) {
            $statements[] = sprintf('DROP VIEW IF EXISTS %s', $viewName);
        }

        // Algorithm is silently ignored on SQLite
        $statements[] = sprintf('CREATE VIEW %s AS %s', $viewName, $operation->getStatement());

        return $statements;
    }

    /**
     * @inheritDoc
     */
    protected function compileAlterView(AlterView $operation): array
    {
        $viewName = $this->quoteIdentifier($operation->getObjectName());

        // SQLite does not support ALTER VIEW, so DROP + CREATE
        return [
            sprintf('DROP VIEW IF EXISTS %s', $viewName),
            sprintf('CREATE VIEW %s AS %s', $viewName, $operation->getStatement()),
        ];
    }
}
