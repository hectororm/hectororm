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

namespace Hector\Schema\Plan;

use ArrayIterator;
use Countable;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use IteratorAggregate;


/**
 * @implements IteratorAggregate<int, OperationInterface>
 */
class Plan implements Countable, IteratorAggregate
{
    /** @var OperationInterface[] */
    private array $entries = [];

    /**
     * Resolve a table/view name from a string or Table object.
     *
     * @param string|Table $table
     *
     * @return string
     */
    private function resolveTableName(string|Table $table): string
    {
        return $table instanceof Table ? $table->getName() : $table;
    }

    /**
     * Add an operation to the plan.
     *
     * @param OperationInterface $operation
     *
     * @return static
     */
    public function add(OperationInterface $operation): static
    {
        $this->entries[] = $operation;

        return $this;
    }

    /**
     * Create a new table.
     *
     * @param string $name
     * @param callable|null $callback
     * @param string|null $charset
     * @param string|null $collation
     * @param bool $ifNotExists
     *
     * @return static|CreateTable
     */
    public function create(
        string $name,
        ?callable $callback = null,
        ?string $charset = null,
        ?string $collation = null,
        bool $ifNotExists = false,
    ): static|CreateTable {
        $createTable = new CreateTable(
            name: $name,
            charset: $charset,
            collation: $collation,
            ifNotExists: $ifNotExists,
        );

        $this->entries[] = $createTable;

        if (null !== $callback) {
            $callback($createTable);

            return $this;
        }

        return $createTable;
    }

    /**
     * Alter an existing table.
     *
     * @param string|Table $table
     * @param callable|null $callback
     *
     * @return static|AlterTable
     */
    public function alter(string|Table $table, ?callable $callback = null): static|AlterTable
    {
        $tableName = $this->resolveTableName($table);
        $alterTable = new AlterTable($tableName);
        $this->entries[] = $alterTable;

        if (null !== $callback) {
            $callback($alterTable);

            return $this;
        }

        return $alterTable;
    }

    /**
     * Drop a table.
     *
     * @param string|Table $table
     * @param bool $ifExists
     *
     * @return static
     */
    public function drop(string|Table $table, bool $ifExists = false): static
    {
        $tableName = $this->resolveTableName($table);

        $this->entries[] = new DropTable(
            table: $tableName,
            ifExists: $ifExists,
        );

        return $this;
    }

    /**
     * Rename a table.
     *
     * @param string|Table $table
     * @param string $newName
     *
     * @return static
     */
    public function rename(string|Table $table, string $newName): static
    {
        $tableName = $this->resolveTableName($table);

        $alterTable = new AlterTable($tableName);
        $alterTable->renameTable($newName);
        $this->entries[] = $alterTable;

        return $this;
    }

    /**
     * Migrate data from one table to another.
     *
     * @param string|Table $source Source table
     * @param string|Table $target Destination table
     * @param array<string, string> $columnMapping Column mapping ['source_col' => 'target_col', ...], empty for SELECT *
     *
     * @return static
     */
    public function migrate(string|Table $source, string|Table $target, array $columnMapping = []): static
    {
        $sourceName = $this->resolveTableName($source);
        $targetName = $this->resolveTableName($target);

        $this->entries[] = new MigrateData(
            table: $sourceName,
            targetTable: $targetName,
            columnMapping: $columnMapping,
        );

        return $this;
    }

    /**
     * Add a raw SQL statement to be executed as-is.
     *
     * Raw statements bypass the compiler and are emitted verbatim
     * during the structure pass of plan compilation. They are useful
     * for SQL features not covered by the plan API (e.g., fulltext
     * indexes, triggers, engine changes, etc.).
     *
     * An optional driver filter can restrict execution to specific
     * database drivers (e.g., ['mysql', 'mariadb']). When null,
     * the statement is emitted for all drivers.
     *
     * @param string $statement SQL statement
     * @param string[]|null $drivers Driver filter (null = all drivers)
     *
     * @return static
     */
    public function raw(string $statement, ?array $drivers = null): static
    {
        $this->entries[] = new RawStatement($statement, $drivers);

        return $this;
    }

    /**
     * Create a view.
     *
     * @param string $name
     * @param string $statement SQL SELECT statement
     * @param bool $orReplace
     * @param string|null $algorithm MySQL-specific algorithm (UNDEFINED, MERGE, TEMPTABLE)
     *
     * @return static
     */
    public function createView(
        string $name,
        string $statement,
        bool $orReplace = false,
        ?string $algorithm = null,
    ): static {
        $this->entries[] = new CreateView(
            view: $name,
            statement: $statement,
            orReplace: $orReplace,
            algorithm: $algorithm,
        );

        return $this;
    }

    /**
     * Drop a view.
     *
     * @param string|Table $view
     * @param bool $ifExists
     *
     * @return static
     */
    public function dropView(string|Table $view, bool $ifExists = false): static
    {
        $viewName = $this->resolveTableName($view);

        $this->entries[] = new DropView(
            view: $viewName,
            ifExists: $ifExists,
        );

        return $this;
    }

    /**
     * Alter a view (replace its SELECT statement).
     *
     * @param string|Table $view
     * @param string $statement SQL SELECT statement
     * @param string|null $algorithm MySQL-specific algorithm (UNDEFINED, MERGE, TEMPTABLE)
     *
     * @return static
     */
    public function alterView(string|Table $view, string $statement, ?string $algorithm = null): static
    {
        $viewName = $this->resolveTableName($view);

        $this->entries[] = new AlterView(
            view: $viewName,
            statement: $statement,
            algorithm: $algorithm,
        );

        return $this;
    }

    /**
     * Create a trigger.
     *
     * @param string $name
     * @param string|Table $table
     * @param string $timing BEFORE, AFTER or INSTEAD OF
     * @param string $event INSERT, UPDATE or DELETE
     * @param string $body SQL body of the trigger
     * @param string|null $when Optional WHEN condition (SQLite only)
     *
     * @return static
     */
    public function createTrigger(
        string $name,
        string|Table $table,
        string $timing,
        string $event,
        string $body,
        ?string $when = null,
    ): static {
        $tableName = $this->resolveTableName($table);

        $this->entries[] = new CreateTrigger(
            table: $tableName,
            name: $name,
            timing: $timing,
            event: $event,
            body: $body,
            when: $when,
        );

        return $this;
    }

    /**
     * Drop a trigger.
     *
     * @param string $name
     * @param string|Table $table
     *
     * @return static
     */
    public function dropTrigger(string $name, string|Table $table): static
    {
        $tableName = $this->resolveTableName($table);

        $this->entries[] = new DropTrigger(
            table: $tableName,
            name: $name,
        );

        return $this;
    }

    /**
     * Get a copy of all entries.
     *
     * @return OperationInterface[]
     */
    public function getArrayCopy(): array
    {
        return $this->entries;
    }

    /**
     * @inheritDoc
     *
     * @return ArrayIterator<int, OperationInterface>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->entries);
    }

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return [] === $this->entries;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Get all SQL statements for the plan.
     *
     * Delegates to the compiler which automatically reorders operations:
     * 1. Pre-operations: DisableForeignKeyChecks, DROP FOREIGN KEY, DROP TRIGGER
     * 2. Structure operations + raw statements (in declaration order)
     * 3. Post-operations: ADD FOREIGN KEY, CREATE TRIGGER, EnableForeignKeyChecks
     *
     * When a schema is provided, the compiler may use it to introspect the database
     * and adapt the compilation strategy (e.g., table rebuild for SQLite, index existence checks).
     * Without a schema, all operations are assumed to be natively supported.
     *
     * @param CompilerInterface $compiler
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    public function getStatements(CompilerInterface $compiler, ?Schema $schema = null): iterable
    {
        return $compiler->compile($this, $schema);
    }
}
