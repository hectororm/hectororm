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

namespace Hector\Schema\Plan\Compiler\Dialect;

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Compiler;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\TableRebuilder;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyCharset;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Plan\OperationInterface;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;

/**
 * SQLite dialect.
 */
final class SqliteDialect extends AbstractDialect
{
    private readonly TableRebuilder $rebuilder;

    public function __construct(?DriverCapabilities $capabilities = null)
    {
        parent::__construct($capabilities);

        $this->rebuilder = new TableRebuilder(
            createIndexCompiler: fn(string $table, AddIndex $index): string => $this->compileCreateIndex($table, $index),
            planCompiler: fn(Plan $plan, CompilationContext $context): iterable => (new Compiler($this))->compilePlan(
                $plan,
                $context,
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function driverNames(): array
    {
        return ['sqlite'];
    }

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
    public function inlinesCreateTableForeignKeys(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function supportsAlterForeignKey(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function compileStandaloneOperation(OperationInterface $operation): iterable
    {
        $tableName = $operation->getObjectName();

        if (null === $tableName) {
            return [];
        }

        return $this->compileAlterOperation($tableName, $operation, null);
    }

    /**
     * @inheritDoc
     */
    public function compileDisableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = OFF';
    }

    /**
     * @inheritDoc
     */
    public function compileEnableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = ON';
    }

    /**
     * @inheritDoc
     */
    public function compileCreateTable(CreateTable $createTable): iterable
    {
        $tableName = $this->quoteIdentifier($createTable->getObjectName());
        $operations = $createTable->getArrayCopy();

        // Column definitions
        $definitions = array_map(
            fn(AddColumn $op): string => $this->compileColumnDefinition($op),
            array_filter($operations, fn($op): bool => $op instanceof AddColumn),
        );

        // Check if any column has autoIncrement (already has inline PRIMARY KEY)
        $addColumns = array_filter($operations, fn($op): bool => $op instanceof AddColumn);
        $hasAutoIncrement = (bool)array_filter($addColumns, fn(AddColumn $op): bool => true === $op->isAutoIncrement());

        // Primary key inline (skip if autoIncrement handles it)
        if (false === $hasAutoIncrement) {
            array_push($definitions, ...array_map(
                fn(AddIndex $op): string => sprintf('PRIMARY KEY (%s)', $this->quoteIdentifiers($op->getColumns())),
                array_filter($operations, fn($op): bool => $op instanceof AddIndex && Index::PRIMARY === $op->getType()),
            ));
        }

        // Foreign keys must be inlined into the CREATE TABLE body: SQLite has no
        // ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY syntax. The matching Post
        // pass is skipped via inlinesCreateTableForeignKeys().
        array_push($definitions, ...array_map(
            fn(AddForeignKey $op): string => $this->compileForeignKeyDefinition($op),
            array_filter($operations, fn($op): bool => $op instanceof AddForeignKey),
        ));

        $statements = [];

        if ([] !== $definitions) {
            $statements[] = sprintf(
                "CREATE TABLE %s%s (\n  %s\n)",
                $createTable->ifNotExists() ? 'IF NOT EXISTS ' : '',
                $tableName,
                implode(",\n  ", $definitions),
            );
        }

        // Non-primary indexes must be created separately in SQLite
        array_push($statements, ...array_map(
            fn(AddIndex $op): string => $this->compileCreateIndex($createTable->getObjectName(), $op),
            array_filter($operations, fn($op): bool => $op instanceof AddIndex && Index::PRIMARY !== $op->getType()),
        ));

        return $statements;
    }

    /**
     * @inheritDoc
     */
    public function compileAlterTable(AlterTable $alterTable, CompilationContext $context): iterable
    {
        // Validate operations
        $this->validateAlterOperations($alterTable);

        if (null !== $context->schema && $this->rebuilder->isRequired($alterTable)) {
            yield from $this->rebuilder->compile($alterTable, $context);

            // Emit rename after rebuild if present
            foreach ($alterTable as $operation) {
                if ($operation instanceof RenameTable) {
                    yield sprintf(
                        'ALTER TABLE %s RENAME TO %s',
                        $this->quoteIdentifier($alterTable->getObjectName()),
                        $this->quoteIdentifier($operation->getNewName()),
                    );
                }
            }

            return;
        }

        $renameOperation = null;

        foreach ($alterTable as $operation) {
            if ($operation instanceof PreOperationInterface ||
                $operation instanceof PostOperationInterface) {
                continue;
            }

            if ($operation instanceof RenameTable) {
                $renameOperation = $operation;
                continue;
            }

            yield from $this->compileAlterOperation($alterTable->getObjectName(), $operation, $context->schema);
        }

        // Rename must be emitted as a separate statement after other operations
        if (null !== $renameOperation) {
            yield sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $this->quoteIdentifier($alterTable->getObjectName()),
                $this->quoteIdentifier($renameOperation->getNewName()),
            );
        }
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
    private function compileAlterOperation(
        string $tableName,
        OperationInterface $operation,
        ?Schema $schema
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
            RenameTable::class => [], // Handled separately in compileAlterTable()
            ModifyCharset::class => [], // Silently ignored on SQLite
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
    private function compileAlterAddIndex(AddIndex $operation, string $tableName, ?Schema $schema): array
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
     * Compile a column definition fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    private function compileColumnDefinition(AddColumn|ModifyColumn $operation): string
    {
        $autoIncrement = true === $operation->isAutoIncrement();

        $parts = [
            $this->quoteIdentifier($operation->getName()),
            // SQLite only allows AUTOINCREMENT on an "INTEGER PRIMARY KEY"; the
            // introspected type may be a synonym (e.g. "int"), which SQLite
            // rejects, so force the exact "INTEGER" keyword in that case.
            $autoIncrement ? 'INTEGER' : $operation->getType(),
        ];

        if (false === $operation->isNullable()) {
            $parts[] = 'NOT NULL';
        }

        $default = $this->compileDefault($operation);
        if ('' !== $default) {
            $parts[] = trim($default);
        }

        if (true === $autoIncrement) {
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
    private function compileCreateIndex(string $tableName, AddIndex $operation): string
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
    public function compileCreateView(CreateView $createView): iterable
    {
        $viewName = $this->quoteIdentifier($createView->getObjectName());
        $statements = [];

        // SQLite does not support OR REPLACE for views, so DROP first
        if (true === $createView->orReplace()) {
            $statements[] = sprintf('DROP VIEW IF EXISTS %s', $viewName);
        }

        // Algorithm is silently ignored on SQLite
        $statements[] = sprintf('CREATE VIEW %s AS %s', $viewName, $createView->getStatement());

        return $statements;
    }

    /**
     * @inheritDoc
     */
    public function compileAlterView(AlterView $alterView): iterable
    {
        $viewName = $this->quoteIdentifier($alterView->getObjectName());

        // SQLite does not support ALTER VIEW, so DROP + CREATE
        return [
            sprintf('DROP VIEW IF EXISTS %s', $viewName),
            sprintf('CREATE VIEW %s AS %s', $viewName, $alterView->getStatement()),
        ];
    }

    /**
     * @inheritDoc
     */
    public function compileCreateTrigger(CreateTrigger $trigger): string
    {
        $sql = sprintf(
            'CREATE TRIGGER IF NOT EXISTS %s %s %s ON %s FOR EACH ROW',
            $this->quoteIdentifier($trigger->getName()),
            $trigger->getTiming(),
            $trigger->getEvent(),
            $this->quoteIdentifier($trigger->getObjectName()),
        );

        if (null !== $trigger->getWhen()) {
            $sql .= sprintf(' WHEN %s', $trigger->getWhen());
        }

        $body = rtrim($trigger->getBody(), "; \t\n\r\0\x0B");
        $sql .= sprintf(' BEGIN %s; END', $body);

        return $sql;
    }
}
