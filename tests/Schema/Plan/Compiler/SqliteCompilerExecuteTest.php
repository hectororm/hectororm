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
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TableOperation;

/**
 * Class SqliteCompilerExecuteTest.
 *
 * Executes DDL plans against an in-memory SQLite database.
 * Always available (no external database required).
 */
class SqliteCompilerExecuteTest extends AbstractCompilerExecuteTestCase
{
    /**
     * @inheritDoc
     */
    protected static function createConnection(): ?Connection
    {
        $connection = new Connection('sqlite::memory:');

        // Enable foreign key support
        $connection->execute('PRAGMA foreign_keys = ON');

        return $connection;
    }

    /**
     * @inheritDoc
     */
    protected static function createGenerator(Connection $connection): GeneratorInterface
    {
        return new Sqlite($connection);
    }

    /**
     * @inheritDoc
     */
    protected static function getSchemaName(): string
    {
        return 'main';
    }

    /**
     * Test that modifying a column triggers the table rebuild mechanism
     * and the resulting table has the correct schema.
     */
    public function testModifyColumnViaRebuild(): void
    {
        $connection = static::createConnection();
        static::$connection = $connection;

        // Create a table with a VARCHAR(100) column
        $plan = new Plan();
        $plan->create('rebuild_test', function (TableOperation $t): void {
            $t->addColumn('id', 'INTEGER', autoIncrement: true)
                ->addColumn('name', 'VARCHAR(100)')
                ->addColumn('email', 'VARCHAR(255)')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
        });
        static::executePlan($plan, $connection);

        // Insert test data
        $connection->execute("INSERT INTO rebuild_test (name, email) VALUES ('Alice', 'alice@example.com')");
        $connection->execute("INSERT INTO rebuild_test (name, email) VALUES ('Bob', 'bob@example.com')");

        // Modify the column type — triggers rebuild
        $plan2 = new Plan();
        $plan2->alter('rebuild_test')
            ->modifyColumn('name', 'TEXT');

        // For rebuild, we need the schema
        $compiler = new SqliteCompiler();
        $generator = new Sqlite($connection);
        $schema = $generator->generateSchema('main');

        foreach ($plan2->getStatements($compiler, $schema) as $statement) {
            $connection->execute($statement);
        }

        // Verify table still exists with correct columns
        $schema = $generator->generateSchema('main');
        $table = $schema->getTable('rebuild_test');

        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);

        // Verify column type was changed
        $nameColumn = $table->getColumn('name');
        $this->assertSame('text', strtolower($nameColumn->getType()));

        // Verify data was preserved
        $rows = $connection->fetchAll('SELECT name, email FROM rebuild_test ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('alice@example.com', $rows[0]['email']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('bob@example.com', $rows[1]['email']);

        // Cleanup
        $cleanPlan = new Plan();
        $cleanPlan->drop('rebuild_test', ifExists: true);
        static::executePlan($cleanPlan, $connection);
    }

    /**
     * Test a mixed alter with add/drop/modify in one TablePlan.
     *
     * The presence of modifyColumn forces a full rebuild, but all operations
     * (addColumn, dropColumn, modifyColumn) must be applied correctly, and
     * existing data must be preserved.
     */
    public function testMixedAlterWithRebuild(): void
    {
        $connection = static::createConnection();
        static::$connection = $connection;

        // Create a table with several columns
        $plan = new Plan();
        $plan->create('mixed_rebuild_test', function (TableOperation $t): void {
            $t->addColumn('id', 'INTEGER', autoIncrement: true)
                ->addColumn('name', 'VARCHAR(100)')
                ->addColumn('legacy', 'VARCHAR(50)')
                ->addColumn('temp', 'VARCHAR(50)')
                ->addColumn('email', 'VARCHAR(255)')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
        });
        static::executePlan($plan, $connection);

        // Insert test data
        $connection->execute("INSERT INTO mixed_rebuild_test (name, legacy, temp, email) VALUES ('Alice', 'old1', 'tmp1', 'alice@example.com')");
        $connection->execute("INSERT INTO mixed_rebuild_test (name, legacy, temp, email) VALUES ('Bob', 'old2', 'tmp2', 'bob@example.com')");

        // Single alter that mixes everything:
        // - addColumn avatar (new)
        // - dropColumn legacy (removed)
        // - modifyColumn name VARCHAR(100) -> TEXT (triggers rebuild)
        // - addColumn bio (new, nullable)
        // - dropColumn temp (removed)
        $plan2 = new Plan();
        $plan2->alter('mixed_rebuild_test')
            ->addColumn('avatar', 'VARCHAR(255)', nullable: true)
            ->dropColumn('legacy')
            ->modifyColumn('name', 'TEXT')
            ->addColumn('bio', 'TEXT', nullable: true)
            ->dropColumn('temp');

        // For rebuild, we need the schema
        $compiler = new SqliteCompiler();
        $generator = new Sqlite($connection);
        $schema = $generator->generateSchema('main');

        foreach ($plan2->getStatements($compiler, $schema) as $statement) {
            $connection->execute($statement);
        }

        // Verify schema
        $schema = $generator->generateSchema('main');
        $table = $schema->getTable('mixed_rebuild_test');

        $columnNames = [];
        foreach ($table->getColumns() as $column) {
            $columnNames[] = $column->getName();
        }

        // Present columns
        $this->assertContains('id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('email', $columnNames);
        $this->assertContains('avatar', $columnNames);
        $this->assertContains('bio', $columnNames);

        // Dropped columns
        $this->assertNotContains('legacy', $columnNames);
        $this->assertNotContains('temp', $columnNames);

        // Verify modified column type
        $nameColumn = $table->getColumn('name');
        $this->assertSame('text', strtolower($nameColumn->getType()));

        // Verify data was preserved for surviving columns
        $rows = $connection->fetchAll('SELECT id, name, email FROM mixed_rebuild_test ORDER BY id');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('alice@example.com', $rows[0]['email']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('bob@example.com', $rows[1]['email']);

        // Cleanup
        $cleanPlan = new Plan();
        $cleanPlan->drop('mixed_rebuild_test', ifExists: true);
        static::executePlan($cleanPlan, $connection);
    }
}
