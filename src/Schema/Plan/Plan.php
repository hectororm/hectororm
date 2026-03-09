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
use Hector\Schema\Plan\Operation\AlterView;
use Hector\Schema\Plan\Operation\CreateTable;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropTable;
use Hector\Schema\Plan\Operation\DropView;
use Hector\Schema\Plan\Operation\MigrateData;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use IteratorAggregate;

class Plan implements Countable, IteratorAggregate
{
    /** @var ObjectPlan[] */
    private array $objectPlans = [];

    /**
     * Create a new table.
     *
     * @param string $name
     * @param callable|null $callback
     * @param string|null $charset
     * @param string|null $collation
     * @param bool $ifNotExists
     *
     * @return static|TablePlan
     */
    public function create(
        string $name,
        ?callable $callback = null,
        ?string $charset = null,
        ?string $collation = null,
        bool $ifNotExists = false,
    ): static|TablePlan {
        $tablePlan = new TablePlan($name);

        // Add CreateTable operation as the first operation
        $tablePlan->addOperation(new CreateTable(
            table: $name,
            charset: $charset,
            collation: $collation,
            ifNotExists: $ifNotExists,
        ));

        $this->objectPlans[] = $tablePlan;

        if (null !== $callback) {
            $callback($tablePlan);

            return $this;
        }

        return $tablePlan;
    }

    /**
     * Alter an existing table.
     *
     * @param string|Table $table
     * @param callable|null $callback
     *
     * @return static|TablePlan
     */
    public function alter(string|Table $table, ?callable $callback = null): static|TablePlan
    {
        $tableName = $table instanceof Table ? $table->getName() : $table;
        $tablePlan = new TablePlan($tableName);
        $this->objectPlans[] = $tablePlan;

        if (null !== $callback) {
            $callback($tablePlan);

            return $this;
        }

        return $tablePlan;
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
        $tableName = $table instanceof Table ? $table->getName() : $table;
        $tablePlan = new TablePlan($tableName);

        $tablePlan->addOperation(new DropTable(
            table: $tableName,
            ifExists: $ifExists,
        ));

        $this->objectPlans[] = $tablePlan;

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
        $tableName = $table instanceof Table ? $table->getName() : $table;
        $tablePlan = new TablePlan($tableName);

        $tablePlan->addOperation(new RenameTable(
            table: $tableName,
            newName: $newName,
        ));

        $this->objectPlans[] = $tablePlan;

        return $this;
    }

    /**
     * Migrate data from one table to another.
     *
     * @param string|Table $source Source table
     * @param string|Table $target Destination table
     * @param array $columnMapping Column mapping ['source_col' => 'target_col', ...], empty for SELECT *
     *
     * @return static
     */
    public function migrate(string|Table $source, string|Table $target, array $columnMapping = []): static
    {
        $sourceName = $source instanceof Table ? $source->getName() : $source;
        $targetName = $target instanceof Table ? $target->getName() : $target;

        $tablePlan = new TablePlan($sourceName);
        $tablePlan->addOperation(new MigrateData(
            table: $sourceName,
            targetTable: $targetName,
            columnMapping: $columnMapping,
        ));

        $this->objectPlans[] = $tablePlan;

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
        $viewPlan = new ViewPlan($name);
        $viewPlan->addOperation(new CreateView(
            view: $name,
            statement: $statement,
            orReplace: $orReplace,
            algorithm: $algorithm,
        ));

        $this->objectPlans[] = $viewPlan;

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
        $viewName = $view instanceof Table ? $view->getName() : $view;
        $viewPlan = new ViewPlan($viewName);
        $viewPlan->addOperation(new DropView(
            view: $viewName,
            ifExists: $ifExists,
        ));

        $this->objectPlans[] = $viewPlan;

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
        $viewName = $view instanceof Table ? $view->getName() : $view;
        $viewPlan = new ViewPlan($viewName);
        $viewPlan->addOperation(new AlterView(
            view: $viewName,
            statement: $statement,
            algorithm: $algorithm,
        ));

        $this->objectPlans[] = $viewPlan;

        return $this;
    }

    /**
     * Get a copy of all object plans.
     *
     * @return ObjectPlan[]
     */
    public function getArrayCopy(): array
    {
        return $this->objectPlans;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->objectPlans);
    }

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->objectPlans);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->objectPlans);
    }

    /**
     * Get all SQL statements for the plan.
     *
     * Operations are automatically reordered:
     * - PreOperationInterface first (e.g. DROP FOREIGN KEY, DROP VIEW)
     * - Structure operations (everything else)
     * - PostOperationInterface last (e.g. ADD FOREIGN KEY, CREATE/ALTER VIEW)
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
        return $this->compileStatements($compiler, $schema);
    }

    /**
     * Compile all statements with Pre/Post ordering.
     *
     * Separates operations by priority and orders them:
     * 1. Pre-operations (e.g. DROP FOREIGN KEY, DROP VIEW)
     * 2. Structure operations (CREATE/ALTER/DROP TABLE, columns, indexes, data)
     * 3. Post-operations (e.g. ADD FOREIGN KEY, CREATE/ALTER VIEW)
     *
     * @param CompilerInterface $compiler
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    private function compileStatements(CompilerInterface $compiler, ?Schema $schema): iterable
    {
        // Pass 1: Pre-operations (DROP FK, DROP VIEW, etc.)
        foreach ($this->objectPlans as $objectPlan) {
            $plan = $objectPlan->filter(PreOperationInterface::class);

            if (true === $plan->isEmpty()) {
                continue;
            }

            yield from $plan->getStatements($compiler, $schema);
        }

        // Pass 2: Structure (everything except Pre and Post operations)
        foreach ($this->objectPlans as $objectPlan) {
            $plan = $objectPlan->without(PreOperationInterface::class)->without(PostOperationInterface::class);

            if (true === $plan->isEmpty()) {
                continue;
            }

            yield from $plan->getStatements($compiler, $schema);
        }

        // Pass 3: Post-operations (ADD FK, CREATE/ALTER VIEW, etc.)
        foreach ($this->objectPlans as $objectPlan) {
            $plan = $objectPlan->filter(PostOperationInterface::class);

            if (true === $plan->isEmpty()) {
                continue;
            }

            yield from $plan->getStatements($compiler, $schema);
        }
    }
}
