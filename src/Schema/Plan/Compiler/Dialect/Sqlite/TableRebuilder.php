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

namespace Hector\Schema\Plan\Compiler\Dialect\Sqlite;

use Hector\Schema\Column;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;

/**
 * Builds the SQLite "table rebuild" statement sequence.
 *
 * SQLite lacks most ALTER TABLE features (drop/modify column, add/drop FK). The
 * portable workaround is the 12-step "make new table" recipe: create a temp
 * table with the desired shape, copy the data, drop the original, rename the
 * temp, then recreate the (non-primary) indexes.
 *
 * This class isolates that algorithm out of the dialect. It introspects the
 * current table into a small in-memory diff model ({@see TableDiff}), applies
 * the requested operations to it, then emits the rebuild plan.
 */
final class TableRebuilder
{
    /**
     * Operations that SQLite cannot perform through a native ALTER TABLE and
     * therefore require a full table rebuild.
     */
    private const REBUILD_OPERATIONS = [
        ModifyColumn::class,
        DropColumn::class,
        AddForeignKey::class,
        DropForeignKey::class,
    ];

    /**
     * @param callable(string, AddIndex): string $createIndexCompiler Compiles a single CREATE INDEX statement.
     * @param callable(Plan, CompilationContext): iterable<string> $planCompiler Compiles the inner rebuild plan.
     */
    public function __construct(
        private $createIndexCompiler,
        private $planCompiler,
    ) {
    }

    /**
     * Whether the given ALTER TABLE requires a rebuild for SQLite.
     *
     * @param AlterTable $alterTable
     *
     * @return bool
     */
    public function isRequired(AlterTable $alterTable): bool
    {
        foreach ($alterTable as $operation) {
            if (in_array($operation::class, self::REBUILD_OPERATIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compile the rebuild statement sequence.
     *
     * Steps:
     * 1. PRAGMA foreign_keys = OFF (skipped when FK checks are managed by the plan)
     * 2. CREATE TABLE temp (new shape, PK + FK inline)
     * 3. INSERT INTO temp (...) SELECT ... FROM original
     * 4. DROP TABLE original
     * 5. ALTER TABLE temp RENAME TO original
     * 6. Recreate non-primary indexes
     * 7. PRAGMA foreign_keys = ON (skipped when FK checks are managed by the plan)
     *
     * @param AlterTable         $alterTable
     * @param CompilationContext $context Must carry a schema.
     *
     * @return string[]
     */
    public function compile(AlterTable $alterTable, CompilationContext $context): array
    {
        $schema = $context->schema;

        if (null === $schema) {
            return [];
        }

        $tableName = $alterTable->getObjectName();
        $tempName = sprintf('__htemp_%s_%s', substr(bin2hex(random_bytes(2)), 0, 3), $tableName);

        $diff = $this->buildDiff($schema, $tableName);

        foreach ($alterTable as $operation) {
            $diff->apply($operation);
        }

        $statements = [];

        if (false === $context->foreignKeyChecksManaged) {
            $statements[] = 'PRAGMA foreign_keys = OFF';
        }

        array_push($statements, ...$this->compileRebuildPlan($diff, $tableName, $tempName, $schema, $context));

        // Recreate non-primary indexes AFTER the DROP + RENAME: SQLite index
        // names are global to the database, so they can only be reused once the
        // original table (and its indexes) is gone and the temp table is renamed.
        foreach ($diff->nonPrimaryIndexes() as $index) {
            $statements[] = ($this->createIndexCompiler)($tableName, $index->toAddIndex($tableName));
        }

        if (false === $context->foreignKeyChecksManaged) {
            $statements[] = 'PRAGMA foreign_keys = ON';
        }

        return $statements;
    }

    /**
     * Introspect the current table into a mutable diff model.
     *
     * @param Schema $schema
     * @param string $tableName
     *
     * @return TableDiff
     */
    private function buildDiff(Schema $schema, string $tableName): TableDiff
    {
        $table = $schema->getTable($tableName);

        $columns = [];
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = ColumnDef::fromSchema($column, $this->reconstructColumnType($column));
        }

        $indexes = [];
        foreach ($table->getIndexes() as $index) {
            $indexes[$index->getName()] = new IndexDef(
                $index->getName(),
                $index->getColumnsName(),
                $index->getType(),
            );
        }

        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $fk) {
            $foreignKeys[$fk->getName()] = new ForeignKeyDef(
                $fk->getName(),
                $fk->getColumnsName(),
                $fk->getReferencedTableName(),
                $fk->getReferencedColumnsName(),
                $fk->getUpdateRule(),
                $fk->getDeleteRule(),
            );
        }

        return new TableDiff($columns, $indexes, $foreignKeys);
    }

    /**
     * Build and compile the sub-plan that creates the temp table, migrates the
     * data, drops the original and renames the temp table into place.
     *
     * @param TableDiff          $diff
     * @param string             $tableName
     * @param string             $tempName
     * @param Schema             $schema
     * @param CompilationContext $context
     *
     * @return string[]
     */
    private function compileRebuildPlan(
        TableDiff $diff,
        string $tableName,
        string $tempName,
        Schema $schema,
        CompilationContext $context,
    ): array {
        $columns = $diff->columns();
        $primaryIndexes = $diff->primaryIndexes();
        $foreignKeys = $diff->foreignKeys();

        $plan = new Plan();
        $plan->create($tempName, function (CreateTable $t) use ($columns, $primaryIndexes, $foreignKeys): void {
            foreach ($columns as $col) {
                $t->addColumn(
                    name: $col->name,
                    type: $col->type,
                    nullable: $col->nullable,
                    default: $col->default,
                    hasDefault: $col->hasDefault,
                    autoIncrement: $col->autoIncrement,
                );
            }

            foreach ($primaryIndexes as $idx) {
                $t->addIndex(name: $idx->name, columns: $idx->columns, type: $idx->type);
            }

            foreach ($foreignKeys as $fk) {
                $t->addForeignKey(
                    name: $fk->name,
                    columns: $fk->columns,
                    referencedTable: $fk->referencedTable,
                    referencedColumns: $fk->referencedColumns,
                    onUpdate: $fk->onUpdate,
                    onDelete: $fk->onDelete,
                );
            }
        });

        $plan->migrate($tableName, $tempName, $diff->migrateMapping());
        $plan->drop($tableName);
        $plan->rename($tempName, $tableName);

        // FK-check PRAGMAs are already emitted by the caller around the whole
        // rebuild, so mark them as managed to avoid the inner plan re-emitting
        // (or prematurely toggling) foreign_keys.
        $innerContext = $context->withForeignKeyChecksManaged(true);

        return iterator_to_array(($this->planCompiler)($plan, $innerContext), false);
    }

    /**
     * Reconstruct the SQL type of an introspected column for a table rebuild.
     *
     * Preserves the length parameters lost otherwise: string length
     * (e.g. `VARCHAR(255)`) and numeric precision/scale (e.g. `DECIMAL(10,2)`).
     *
     * @param Column $column
     *
     * @return string
     */
    private function reconstructColumnType(Column $column): string
    {
        $type = $column->getType();

        if (null !== $column->getMaxlength()) {
            return $type . '(' . $column->getMaxlength() . ')';
        }

        $precision = $column->getNumericPrecision();

        if (null !== $precision) {
            $scale = $column->getNumericScale();

            return null !== $scale
                ? $type . '(' . $precision . ',' . $scale . ')'
                : $type . '(' . $precision . ')';
        }

        return $type;
    }
}
