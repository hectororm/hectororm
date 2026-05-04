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

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropTrigger;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\OperationGroupInterface;
use Hector\Schema\Plan\OperationInterface;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\RawStatement;
use Hector\Schema\Schema;

abstract class AbstractCompiler implements CompilerInterface
{
    public function __construct(
        protected ?DriverCapabilities $capabilities = null,
    ) {
    }

    /**
     * Whether FK checks are explicitly managed by the plan.
     *
     * Set to true when the plan contains a DisableForeignKeyChecks entry,
     * so that internal mechanisms (e.g., SQLite table rebuild) can skip
     * their own PRAGMA foreign_keys statements to avoid duplication and
     * premature re-enabling.
     */
    protected bool $foreignKeyChecksManaged = false;

    /**
     * @inheritDoc
     */
    public function compile(Plan $plan, ?Schema $schema = null): iterable
    {
        // Detect if FK checks are explicitly managed by the plan
        $this->foreignKeyChecksManaged = false;
        foreach ($plan as $entry) {
            if ($entry instanceof DisableForeignKeyChecks) {
                $this->foreignKeyChecksManaged = true;
                break;
            }
        }

        // Pass 1: Pre-operations (DisableForeignKeyChecks, DROP FK, DROP TRIGGER, etc.)
        foreach ($plan as $entry) {
            if ($entry instanceof PreOperationInterface) {
                yield from $this->compileOperation($entry, $schema);
                continue;
            }

            if ($entry instanceof OperationGroupInterface) {
                foreach ($entry as $operation) {
                    if ($operation instanceof PreOperationInterface) {
                        yield from $this->compileOperation($operation, $schema);
                    }
                }
            }
        }

        // Pass 2: Structure operations (skip autonomous Pre/Post entries)
        foreach ($plan as $entry) {
            if ($entry instanceof PreOperationInterface || $entry instanceof PostOperationInterface) {
                continue;
            }

            yield from $this->compileOperation($entry, $schema);
        }

        // Pass 3: Post-operations (ADD FK, CREATE TRIGGER, EnableForeignKeyChecks, etc.)
        foreach ($plan as $entry) {
            if ($entry instanceof PostOperationInterface) {
                yield from $this->compileOperation($entry, $schema);
                continue;
            }

            if ($entry instanceof OperationGroupInterface) {
                foreach ($entry as $operation) {
                    if ($operation instanceof PostOperationInterface) {
                        yield from $this->compileOperation($operation, $schema);
                    }
                }
            }
        }
    }

    /**
     * Get the driver names handled by this compiler.
     *
     * Used to filter RawStatement entries that are scoped to specific drivers.
     * Values must match those returned by Connection\Driver\DriverInfo::getDriver().
     *
     * @return string[]
     */
    abstract protected function getDriverNames(): array;

    /**
     * Compile a single operation into SQL statements.
     *
     * Dispatches by operation type. Handles both autonomous plan entries
     * and sub-operations extracted from groups (Pre/Post passes).
     *
     * @param OperationInterface $operation
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    protected function compileOperation(OperationInterface $operation, ?Schema $schema = null): iterable
    {
        switch (true) {
            // FK checks
            case $operation instanceof DisableForeignKeyChecks:
                yield $this->compileDisableForeignKeyChecks();
                break;
            case $operation instanceof EnableForeignKeyChecks:
                yield $this->compileEnableForeignKeyChecks();
                break;

            // Raw statements
            case $operation instanceof RawStatement:
                $drivers = $operation->getDrivers();

                if (null === $drivers || [] !== array_intersect($drivers, $this->getDriverNames())) {
                    yield $operation->getStatement();
                }
                break;

            // Table operations
            case $operation instanceof CreateTable:
                yield from $this->compileCreateTable($operation);
                break;
            case $operation instanceof AlterTable:
                yield from $this->compileAlterTable($operation, $schema);
                break;
            case $operation instanceof DropTable:
                yield $this->compileDropTable($operation);
                break;

            // Data migration
            case $operation instanceof MigrateData:
                yield $this->compileMigrateData($operation);
                break;

            // Views
            case $operation instanceof CreateView:
                yield from $this->compileCreateView($operation);
                break;
            case $operation instanceof AlterView:
                yield from $this->compileAlterView($operation);
                break;
            case $operation instanceof DropView:
                yield $this->compileDropView($operation);
                break;

            // Triggers
            case $operation instanceof CreateTrigger:
                yield $this->compileCreateTrigger($operation);
                break;
            case $operation instanceof DropTrigger:
                yield $this->compileDropTrigger($operation);
                break;

            // Foreign keys (sub-operations from groups, compiled in Pre/Post passes)
            case $operation instanceof AddForeignKey:
                yield sprintf(
                    'ALTER TABLE %s ADD %s',
                    $this->quoteIdentifier($operation->getObjectName()),
                    $this->compileForeignKeyDefinition($operation),
                );
                break;
            case $operation instanceof DropForeignKey:
                yield sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quoteIdentifier($operation->getObjectName()),
                    $this->quoteIdentifier($operation->getName()),
                );
                break;

            // Standalone sub-operations (e.g., AddColumn added directly to the plan)
            default:
                if (false === ($operation instanceof OperationGroupInterface)) {
                    yield from $this->compileStandaloneOperation($operation);
                }
        }
    }

    /**
     * Compile a standalone sub-operation (added directly to the plan, not in a group).
     *
     * @param OperationInterface $operation
     *
     * @return iterable<string>
     */
    abstract protected function compileStandaloneOperation(OperationInterface $operation): iterable;

    /**
     * Compile the SQL to disable foreign key checks.
     *
     * @return string
     */
    abstract protected function compileDisableForeignKeyChecks(): string;

    /**
     * Compile the SQL to enable foreign key checks.
     *
     * @return string
     */
    abstract protected function compileEnableForeignKeyChecks(): string;

    /**
     * Compile a CREATE TABLE statement with all its columns, indexes and foreign keys.
     *
     * @param CreateTable $createTable
     *
     * @return iterable<string>
     */
    abstract protected function compileCreateTable(CreateTable $createTable): iterable;

    /**
     * Compile ALTER TABLE statements from an operation group.
     *
     * @param AlterTable $alterTable
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    abstract protected function compileAlterTable(AlterTable $alterTable, ?Schema $schema = null): iterable;

    /**
     * Compile a DROP TABLE statement.
     *
     * @param DropTable $dropTable
     *
     * @return string
     */
    protected function compileDropTable(DropTable $dropTable): string
    {
        return sprintf(
            'DROP TABLE %s%s',
            $dropTable->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($dropTable->getObjectName()),
        );
    }

    /**
     * Compile a CREATE VIEW statement.
     *
     * @param CreateView $createView
     *
     * @return iterable<string>
     */
    abstract protected function compileCreateView(CreateView $createView): iterable;

    /**
     * Compile a DROP VIEW statement.
     *
     * @param DropView $dropView
     *
     * @return string
     */
    protected function compileDropView(DropView $dropView): string
    {
        return sprintf(
            'DROP VIEW %s%s',
            $dropView->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($dropView->getObjectName()),
        );
    }

    /**
     * Compile an ALTER VIEW statement.
     *
     * @param AlterView $alterView
     *
     * @return iterable<string>
     */
    abstract protected function compileAlterView(AlterView $alterView): iterable;

    /**
     * Compile a CREATE TRIGGER statement.
     *
     * @param CreateTrigger $trigger
     *
     * @return string
     */
    abstract protected function compileCreateTrigger(CreateTrigger $trigger): string;

    /**
     * Compile a DROP TRIGGER statement.
     *
     * @param DropTrigger $trigger
     *
     * @return string
     */
    protected function compileDropTrigger(DropTrigger $trigger): string
    {
        return sprintf(
            'DROP TRIGGER IF EXISTS %s',
            $this->quoteIdentifier($trigger->getName()),
        );
    }

    /**
     * Compile a column definition fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    abstract protected function compileColumnDefinition(AddColumn|ModifyColumn $operation): string;

    /**
     * Compile a MIGRATE DATA statement (INSERT INTO ... SELECT ...).
     *
     * @param MigrateData $migrateData
     *
     * @return string
     */
    protected function compileMigrateData(MigrateData $migrateData): string
    {
        $mapping = $migrateData->getColumnMapping();

        if ([] === $mapping) {
            return sprintf(
                'INSERT INTO %s SELECT * FROM %s',
                $this->quoteIdentifier($migrateData->getTargetTable()),
                $this->quoteIdentifier($migrateData->getObjectName()),
            );
        }

        $targetColumns = $this->quoteIdentifiers(array_values($mapping));
        $sourceColumns = $this->quoteIdentifiers(array_keys($mapping));

        return sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $this->quoteIdentifier($migrateData->getTargetTable()),
            $targetColumns,
            $sourceColumns,
            $this->quoteIdentifier($migrateData->getObjectName()),
        );
    }

    /**
     * Get the identifier quote character for this dialect.
     *
     * @return string
     */
    abstract protected function getIdentifierQuote(): string;

    /**
     * Quote an identifier.
     *
     * @param string $name
     *
     * @return string
     */
    protected function quoteIdentifier(string $name): string
    {
        $quote = $this->getIdentifierQuote();

        return sprintf('%1$s%2$s%1$s', $quote, str_replace($quote, $quote . $quote, $name));
    }

    /**
     * Quote a list of identifiers.
     *
     * @param string[] $names
     *
     * @return string
     */
    protected function quoteIdentifiers(array $names): string
    {
        return implode(', ', array_map(fn(string $name): string => $this->quoteIdentifier($name), $names));
    }

    /**
     * Validate ALTER TABLE operations.
     *
     * Detects operations that are impossible without a database change,
     * and throws a PlanException before any SQL is generated.
     *
     * @param AlterTable $alterTable
     *
     * @throws PlanException
     */
    protected function validateAlterOperations(AlterTable $alterTable): void
    {
        foreach ($alterTable as $operation) {
            if (false === ($operation instanceof AddColumn)) {
                continue;
            }

            if ($operation->isNullable() || $operation->hasDefault() || $operation->isAutoIncrement()) {
                continue;
            }

            throw new PlanException(
                sprintf(
                    'Cannot add NOT NULL column \'%s\' without a default value on an existing table \'%s\'',
                    $operation->getName(),
                    $alterTable->getObjectName(),
                )
            );
        }
    }

    /**
     * Compile the default value fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    protected function compileDefault(AddColumn|ModifyColumn $operation): string
    {
        if (false === $operation->hasDefault()) {
            return '';
        }

        $default = $operation->getDefault();

        if (null === $default) {
            return ' DEFAULT NULL';
        }

        if (is_bool($default)) {
            return sprintf(' DEFAULT %s', $default ? '1' : '0');
        }

        if (is_int($default) || is_float($default)) {
            return sprintf(' DEFAULT %s', $default);
        }

        /** @var string $stringDefault */
        $stringDefault = $default;

        return sprintf(' DEFAULT \'%s\'', str_replace("'", "''", $stringDefault));
    }

    /**
     * Compile a foreign key definition fragment.
     *
     * @param AddForeignKey $operation
     *
     * @return string
     */
    protected function compileForeignKeyDefinition(AddForeignKey $operation): string
    {
        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quoteIdentifier($operation->getName()),
            $this->quoteIdentifiers($operation->getColumns()),
            $this->quoteIdentifier($operation->getReferencedTable()),
            $this->quoteIdentifiers($operation->getReferencedColumns()),
        );

        if ('NO ACTION' !== $operation->getOnUpdate()) {
            $sql .= sprintf(' ON UPDATE %s', $operation->getOnUpdate());
        }

        if ('NO ACTION' !== $operation->getOnDelete()) {
            $sql .= sprintf(' ON DELETE %s', $operation->getOnDelete());
        }

        return $sql;
    }
}
