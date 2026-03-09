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

namespace Hector\Schema\Tests\Plan\Compiler;

use Hector\Connection\Connection;
use Hector\Schema\Generator\GeneratorInterface;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TablePlan;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractCompilerExecuteTestCase.
 *
 * Integration tests that execute DDL plans against a real database connection
 * and verify the resulting schema via the Generator introspection classes.
 *
 * Uses @depends to chain test methods so each step builds on the previous state.
 * If the first test skips (e.g., no MySQL available), all dependents skip automatically.
 */
abstract class AbstractCompilerExecuteTestCase extends TestCase
{
    protected static ?Connection $connection = null;

    /**
     * Create a connection to the test database.
     * Return null to skip all tests (e.g., when MYSQL_DSN is not set).
     *
     * @return Connection|null
     */
    abstract protected static function createConnection(): ?Connection;

    /**
     * Create a generator for introspection.
     *
     * @param Connection $connection
     *
     * @return GeneratorInterface
     */
    abstract protected static function createGenerator(Connection $connection): GeneratorInterface;

    /**
     * Get the schema name for introspection.
     *
     * @return string
     */
    abstract protected static function getSchemaName(): string;

    /**
     * Table name used for all execute tests.
     */
    protected static function getTestTableName(): string
    {
        return 'hector_plan_test';
    }

    /**
     * Execute a plan on the given connection using AutoCompiler.
     *
     * @param Plan $plan
     * @param Connection $connection
     */
    protected static function executePlan(Plan $plan, Connection $connection): void
    {
        $compiler = new AutoCompiler($connection);
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());

        foreach ($plan->getStatements($compiler, $schema) as $statement) {
            $connection->execute($statement);
        }
    }

    /**
     * @afterClass
     */
    public static function tearDownConnectionAndTable(): void
    {
        if (null !== static::$connection) {
            // Best-effort cleanup
            try {
                $plan = new Plan();
                $plan->drop(static::getTestTableName(), ifExists: true);
                static::executePlan($plan, static::$connection);
            } catch (\Throwable) {
            }

            static::$connection = null;
        }
    }

    /**
     * Step 1: Create a table with columns, a primary key, and a unique index.
     */
    public function testCreateTable(): Connection
    {
        $connection = static::createConnection();

        if (null === $connection) {
            $this->markTestSkipped('Database connection not available');
        }

        static::$connection = $connection;

        // Clean up in case a previous run left the table behind
        $cleanPlan = new Plan();
        $cleanPlan->drop(static::getTestTableName(), ifExists: true);
        static::executePlan($cleanPlan, $connection);

        // CREATE TABLE
        $plan = new Plan();
        $plan->create(static::getTestTableName(), function (TablePlan $t) {
            $t->addColumn('id', 'INTEGER', autoIncrement: true)
              ->addColumn('name', 'VARCHAR(100)')
              ->addColumn('email', 'VARCHAR(255)')
              ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
              ->addIndex('idx_email', ['email'], Index::UNIQUE);
        });

        static::executePlan($plan, $connection);

        // Verify via generator
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());

        $this->assertTrue($schema->hasTable(static::getTestTableName()));

        $table = $schema->getTable(static::getTestTableName());

        // Verify columns exist
        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);

        return $connection;
    }

    /**
     * Step 2: Alter table — add a new column.
     *
     * @depends testCreateTable
     */
    public function testAlterAddColumn(Connection $connection): Connection
    {
        $plan = new Plan();
        $plan->alter(static::getTestTableName())
            ->addColumn('bio', 'TEXT', nullable: true);

        static::executePlan($plan, $connection);

        // Verify
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());
        $table = $schema->getTable(static::getTestTableName());

        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }
        $this->assertContains('bio', $columnNames);

        $bioColumn = $table->getColumn('bio');
        $this->assertTrue($bioColumn->isNullable());

        return $connection;
    }

    /**
     * Step 3: Alter table — add a non-unique index on 'name'.
     *
     * @depends testAlterAddColumn
     */
    public function testAlterAddIndex(Connection $connection): Connection
    {
        $plan = new Plan();
        $plan->alter(static::getTestTableName())
            ->addIndex('idx_name', ['name']);

        static::executePlan($plan, $connection);

        // Verify
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());
        $table = $schema->getTable(static::getTestTableName());

        $indexNames = [];
        foreach ($table->getIndexes() as $index) {
            $indexNames[] = $index->getName();
        }
        $this->assertContains('idx_name', $indexNames);

        return $connection;
    }

    /**
     * Step 4: Alter table — rename column 'name' to 'display_name'.
     *
     * @depends testAlterAddIndex
     */
    public function testAlterRenameColumn(Connection $connection): Connection
    {
        $plan = new Plan();
        $plan->alter(static::getTestTableName())
            ->renameColumn('name', 'display_name');

        static::executePlan($plan, $connection);

        // Verify
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());
        $table = $schema->getTable(static::getTestTableName());

        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }
        $this->assertContains('display_name', $columnNames);
        $this->assertNotContains('name', $columnNames);

        return $connection;
    }

    /**
     * Step 5: Alter table — drop index, then drop column 'bio'.
     *
     * @depends testAlterRenameColumn
     */
    public function testAlterDropColumnAndIndex(Connection $connection): Connection
    {
        // Drop the index on the old 'name' column first (now 'display_name')
        $plan = new Plan();
        $plan->alter(static::getTestTableName())
            ->dropIndex('idx_name');

        static::executePlan($plan, $connection);

        // Drop the 'bio' column
        $plan2 = new Plan();
        $plan2->alter(static::getTestTableName())
            ->dropColumn('bio');

        static::executePlan($plan2, $connection);

        // Verify
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());
        $table = $schema->getTable(static::getTestTableName());

        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }
        $this->assertNotContains('bio', $columnNames);

        $indexNames = [];
        foreach ($table->getIndexes() as $index) {
            $indexNames[] = $index->getName();
        }
        $this->assertNotContains('idx_name', $indexNames);

        return $connection;
    }

    /**
     * Step 6: Drop the test table.
     *
     * @depends testAlterDropColumnAndIndex
     */
    public function testDropTable(Connection $connection): void
    {
        $plan = new Plan();
        $plan->drop(static::getTestTableName());

        static::executePlan($plan, $connection);

        // Verify table no longer exists
        $generator = static::createGenerator($connection);
        $schema = $generator->generateSchema(static::getSchemaName());

        $this->assertFalse($schema->hasTable(static::getTestTableName()));
    }
}
