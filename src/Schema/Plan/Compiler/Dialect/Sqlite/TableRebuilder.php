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
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Generator\Sqlite\CreateTableParser;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\Operation\AddCheck;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropCheck;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use Hector\Schema\Table;

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
        AddCheck::class,
        DropCheck::class,
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
            if ($operation instanceof AddColumn && true === $operation->getGenerated()?->isStored()) {
                return true;
            }

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
            throw new PlanException('SQLite table rebuild requires an existing schema');
        }

        $tableName = $alterTable->getObjectName();
        if (false === $schema->hasTable($tableName)) {
            throw new PlanException(sprintf('Cannot rebuild SQLite table "%s": missing from the schema', $tableName));
        }
        $tempName = sprintf('__htemp_%s_%s', substr(bin2hex(random_bytes(2)), 0, 3), $tableName);

        $table = $schema->getTable($tableName);
        if (false === $table->hasCheckMetadata()) {
            throw new PlanException(sprintf('SQLite table "%s" requires complete CHECK metadata to rebuild', $tableName));
        }
        $definition = $table->getDefinition();
        $triggers = $table->getTriggerDefinitions() ?? [];
        $parser = new CreateTableParser();
        $sourceOnly = null !== $definition;
        $changesReferences = false;
        $changedIndexes = [];
        foreach ($alterTable as $operation) {
            if (false === ($operation instanceof AddCheck || $operation instanceof DropCheck ||
                $operation instanceof RenameTable || $operation instanceof AddIndex || $operation instanceof DropIndex)) {
                $sourceOnly = false;
            }
            if ($operation instanceof RenameColumn || $operation instanceof DropColumn) {
                $changesReferences = true;
            }
            if ($operation instanceof AddIndex || $operation instanceof DropIndex) {
                $changedIndexes[$operation->getName()] = true;
                if (str_starts_with(strtolower($operation->getName()), 'sqlite_autoindex_')) {
                    throw new PlanException('Cannot alter an implicit SQLite index independently of its constraint');
                }
                if ($operation instanceof AddIndex && Index::PRIMARY === $operation->getType()) {
                    $sourceOnly = false;
                }
            }
        }
        if (null !== $definition) {
            if (false === $sourceOnly && [] !== ($features = $parser->getUnsupportedRebuildFeatures($definition))) {
                throw new PlanException('Cannot faithfully rebuild SQLite definitions with: ' . implode(', ', $features));
            }
        }
        if ($changesReferences && [] !== $triggers) {
            throw new PlanException('Cannot safely rewrite SQLite triggers during column rename/drop');
        }
        foreach ($schema->getTables(Table::TYPE_VIEW) as $view) {
            $sql = $view->getDefinition();
            if (null !== $sql && $parser->containsIdentifier($sql, $tableName)) {
                throw new PlanException('Cannot rebuild SQLite table with dependent views; drop and recreate them first');
            }
        }
        if ($changesReferences) {
            foreach ($table->getIndexes() as $index) {
                $sql = $index->getDefinition();
                if (null !== $sql && false === isset($changedIndexes[$index->getName()]) &&
                    false === $parser->isSimpleIndex($sql)) {
                    throw new PlanException('Cannot safely rewrite SQLite expression/partial index: ' . $index->getName());
                }
            }
        }

        $diff = $this->buildDiff($schema, $tableName);

        foreach ($alterTable as $operation) {
            $diff->apply($operation);
        }

        if ($changesReferences) {
            $columnNames = array_map('strtolower', array_keys($diff->columns()));
            foreach (array_merge($diff->primaryIndexes(), $diff->nonPrimaryIndexes()) as $index) {
                foreach ($index->columns as $column) {
                    if (false === in_array(strtolower($column), $columnNames, true)) {
                        throw new PlanException('Drop index before dropping its referenced column: ' . $index->name);
                    }
                }
            }
            foreach ($diff->foreignKeys() as $foreignKey) {
                foreach ($foreignKey->columns as $column) {
                    if (false === in_array(strtolower($column), $columnNames, true)) {
                        throw new PlanException('Drop foreign key before dropping its referenced column: ' . $foreignKey->name);
                    }
                }
            }
        }

        $statements = [];

        if (false === $context->foreignKeyChecksManaged) {
            $statements[] = 'PRAGMA foreign_keys = OFF';
        }

        array_push($statements, ...$this->compileRebuildPlan(
            $diff, $tableName, $tempName, $schema, $context,
            $sourceOnly ? $definition : null,
        ));

        // Recreate non-primary indexes AFTER the DROP + RENAME: SQLite index
        // names are global to the database, so they can only be reused once the
        // original table (and its indexes) is gone and the temp table is renamed.
        foreach ($diff->nonPrimaryIndexes() as $index) {
            if ($table->hasIndex($index->name) && false === isset($changedIndexes[$index->name])) {
                $original = $table->getIndex($index->name);
                if ($original->isImplicit()) {
                    continue; // Inline UNIQUE constraints recreate their implicit indexes.
                }
                if (null !== $original->getDefinition() && false === $changesReferences) {
                    $statements[] = $original->getDefinition();
                    continue;
                }
            }
            $statements[] = ($this->createIndexCompiler)($tableName, $index->toAddIndex($tableName));
        }

        array_push($statements, ...array_values($triggers));

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
        $types = null === $table->getDefinition() ? [] :
            (new CreateTableParser())->getColumnTypes($table->getDefinition());
        foreach ($table->getColumns() as $column) {
            $columns[$column->getName()] = ColumnDef::fromSchema(
                $column, $types[$column->getName()] ?? $this->reconstructColumnType($column),
            );
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

        return new TableDiff($columns, $indexes, $foreignKeys, $tableName, iterator_to_array($table->getChecks()));
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
        ?string $sourceSql = null,
    ): array {
        $columns = $diff->columns();
        $primaryIndexes = $diff->primaryIndexes();
        $foreignKeys = $diff->foreignKeys();
        $mapping = $diff->migrateMapping();
        if ([] === $mapping) {
            // An empty mapping means SELECT * to MigrateData, which would write
            // generated columns or copy unrelated values into newly added columns.
            throw new PlanException(sprintf(
                'Cannot rebuild SQLite table "%s": no surviving writable column to migrate',
                $tableName,
            ));
        }

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
                    generated: $col->generated,
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

        // Preserve hidden rowids whenever an alias is available in both shapes.
        $table = $schema->getTable($tableName);
        $definition = $table->getDefinition();
        $parser = new CreateTableParser();
        if (null !== $definition && false === $parser->hasTableOption($definition, 'WITHOUT')) {
            $names = array_map('strtolower', array_merge(array_keys($columns), array_keys($mapping)));
            $rowidAlias = null;
            foreach (['rowid', '_rowid_', 'oid'] as $alias) {
                if (false === in_array($alias, $names, true)) {
                    $rowidAlias = $alias;
                    $mapping = [$alias => $alias] + $mapping;
                    break;
                }
            }
            if (null === $rowidAlias) {
                throw new PlanException('Cannot preserve SQLite rowids: all rowid aliases are shadowed');
            }
        }
        $plan->migrate($tableName, $tempName, $mapping);

        // Retain the AUTOINCREMENT high-water mark, including deleted rows.
        if (null !== $definition && $parser->containsKeyword($definition, 'AUTOINCREMENT')) {
            $old = "'" . str_replace("'", "''", $tableName) . "'";
            $temp = "'" . str_replace("'", "''", $tempName) . "'";
            $plan->raw('UPDATE sqlite_sequence SET seq = MAX(seq, COALESCE(' .
                '(SELECT seq FROM sqlite_sequence WHERE name = ' . $old . '), 0)) WHERE name = ' . $temp);
        }
        $plan->drop($tableName);
        $plan->rename($tempName, $tableName);

        // FK-check PRAGMAs are already emitted by the caller around the whole
        // rebuild, so mark them as managed to avoid the inner plan re-emitting
        // (or prematurely toggling) foreign_keys.
        $innerContext = $context->withForeignKeyChecksManaged(true);

        $statements = iterator_to_array(($this->planCompiler)($plan, $innerContext), false);
        $checks = [];
        foreach ($diff->checks() as $check) {
            if (true !== $check->isEnforced()) {
                throw new PlanException('SQLite rebuild requires known enforced CHECK constraints');
            }
            $checks[] = (null === $check->getName() ? '' :
                'CONSTRAINT "' . str_replace('"', '""', $check->getName()) . '" ') .
                'CHECK (' . $check->getExpression() . ')';
        }
        $statements[0] = $parser->rebuildWithChecks($sourceSql ?? $statements[0], $tempName, $checks);

        if (null === $sourceSql) {
            $implicit = [];
            foreach ($diff->nonPrimaryIndexes() as $index) {
                if ($table->hasIndex($index->name) && $table->getIndex($index->name)->isImplicit()) {
                    $implicit[] = 'UNIQUE (' . implode(', ', array_map(
                        static fn(string $name): string => '"' . str_replace('"', '""', $name) . '"',
                        $index->columns,
                    )) . ')';
                }
            }
            $statements[0] = $parser->rebuildWithChecks($statements[0], $tempName, array_merge($checks, $implicit));
        }

        return $statements;
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
