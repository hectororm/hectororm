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
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Schema\Generator\GeneratorInterface;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Plan\TableOperation;
use PHPUnit\Framework\TestCase;
use Throwable;

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
     * @dataProvider generatedStorageModes
     */
    public function testGeneratedColumnDdlExecution(bool $stored): void
    {
        $connection = static::createConnection();
        if (null === $connection) {
            $this->markTestSkipped('Database connection not available');
        }

        $table = 'hector_generated_ddl_test';
        $clean = new Plan();
        $clean->drop($table, ifExists: true);
        static::executePlan($clean, $connection);

        try {
            $create = new Plan();
            $create->create($table)
                ->addColumn('quantity', 'INTEGER')
                ->addColumn('price', 'INTEGER')
                ->addColumn('total', 'INTEGER', generated: new Generated('quantity * price', stored: $stored));
            static::executePlan($create, $connection);

            $generator = static::createGenerator($connection);
            $schema = $generator->generateSchema(static::getSchemaName());
            $column = $schema->getTable($table)->getColumn('total');
            $this->assertTrue($column->isGenerated());
            $this->assertSame($stored, $column->isGeneratedStored());
            $this->assertFalse($column->hasDefault());
            $this->assertSame('quantity*price', preg_replace('/[\s`()]/', '', $column->getGenerationExpression()));
            $this->assertFalse($schema->getTable($table)->getColumn('quantity')->isGenerated());

            $connection->execute("INSERT INTO $table (quantity, price) VALUES (2, 5)");
            $this->assertSame(10, (int)$connection->fetchOne("SELECT total FROM $table")['total']);
            $connection->execute("UPDATE $table SET quantity = 3");
            $this->assertSame(15, (int)$connection->fetchOne("SELECT total FROM $table")['total']);

            $add = new Plan();
            $add->alter($table)->addColumn('next_total', 'INTEGER', generated: 'quantity * (price + 1)');
            static::executePlan($add, $connection);
            $this->assertSame(18, (int)$connection->fetchOne("SELECT next_total FROM $table")['next_total']);

            if (true === $stored && 'sqlite' !== $connection->getDriverInfo()->getDriver()) {
                $modify = new Plan();
                $modify->alter($table)->modifyColumn('total', 'INTEGER',
                    generated: new Generated('quantity * price + 1', stored: true));
                static::executePlan($modify, $connection);
                $this->assertSame(16, (int)$connection->fetchOne("SELECT total FROM $table")['total']);
            }

            if ('mysql' === $connection->getDriverInfo()->getDriver()) {
                // Execute the legacy rename even on modern MySQL to verify a complete definition.
                $schema = $generator->generateSchema(static::getSchemaName());
                $before = $schema->getTable($table)->getColumn('total');
                $rename = new Plan();
                $rename->alter($table)->renameColumn('total', 'renamed_total');
                $compiler = new MySQLCompiler(new MySQLCapabilities(new DriverInfo('mysql', '5.7.44')));
                foreach ($rename->getStatements($compiler, $schema) as $statement) {
                    $connection->execute($statement);
                }

                $after = $generator->generateSchema(static::getSchemaName())->getTable($table)->getColumn('renamed_total');
                $this->assertSame($before->getGenerationExpression(), $after->getGenerationExpression());
                $this->assertSame($stored, $after->isGeneratedStored());
                $this->assertSame($stored ? 16 : 15,
                    (int)$connection->fetchOne("SELECT renamed_total FROM $table")['renamed_total']);
            }
        } finally {
            static::executePlan($clean, $connection);
        }
    }

    public static function generatedStorageModes(): array
    {
        return [[false], [true]];
    }

    /**
     * @dataProvider currentOnUpdateTypes
     */
    public function testCurrentOnUpdateRoundTrip(string $type, int $precision): void
    {
        $connection = static::createConnection();
        if (null === $connection) {
            $this->markTestSkipped('Database connection not available');
        }

        $sqlite = 'sqlite' === $connection->getDriverInfo()->getDriver();
        $expression = 'CURRENT_TIMESTAMP' . (0 !== $precision ? '(' . $precision . ')' : '');
        $default = new Raw($sqlite ? 'CURRENT_TIMESTAMP' : $expression);
        $tableName = 'hector_on_update_test';
        $clean = new Plan();
        $clean->drop($tableName, ifExists: true);
        static::executePlan($clean, $connection);

        try {
            $plan = new Plan();
            $plan->create($tableName)
                ->addColumn('id', 'INTEGER')
                ->addColumn('value', 'INTEGER')
                ->addColumn('updated_at', $type, default: $default, useCurrentOnUpdate: true);
            static::executePlan($plan, $connection);

            $generator = static::createGenerator($connection);
            $column = $generator->generateSchema(static::getSchemaName())->getTable($tableName)->getColumn('updated_at');
            $this->assertSame($sqlite ? null : $expression, $column->getOnUpdate());
            $this->assertSame($sqlite ? null : $precision, $column->getDatetimePrecision());

            $connection->execute("INSERT INTO $tableName (id, value) VALUES (1, 1)");
            $this->assertNotNull($connection->fetchOne("SELECT updated_at FROM $tableName")['updated_at']);
            $old = '2001-01-01 00:00:00' . (0 !== $precision ? '.000000' : '');
            $connection->execute("UPDATE $tableName SET updated_at = ?", [$old]);
            $connection->execute("UPDATE $tableName SET value = 2");
            $value = $connection->fetchOne("SELECT updated_at FROM $tableName")['updated_at'];
            if ($sqlite) {
                $this->assertSame($old, $value);
            } else {
                $this->assertNotSame($old, $value);
            }

            // Explicit assignments override the automatic update.
            $connection->execute("UPDATE $tableName SET value = 3, updated_at = ?", [$old]);
            $this->assertSame($old, $connection->fetchOne("SELECT updated_at FROM $tableName")['updated_at']);

            // An UPDATE that leaves the other values unchanged must not touch the timestamp.
            $connection->execute("UPDATE $tableName SET value = 3");
            $this->assertSame($old, $connection->fetchOne("SELECT updated_at FROM $tableName")['updated_at']);

            // SQLite rebuilds must also accept the flag. MySQL must retain it on MODIFY.
            $alter = new Plan();
            $alter->alter($tableName)->modifyColumn('updated_at', $type, default: $default, useCurrentOnUpdate: true);
            static::executePlan($alter, $connection);
            $rename = new Plan();
            $rename->alter($tableName)->renameColumn('updated_at', 'modified_at');
            static::executePlan($rename, $connection);
            $column = $generator->generateSchema(static::getSchemaName())->getTable($tableName)->getColumn('modified_at');
            $this->assertSame($sqlite ? null : $expression, $column->getOnUpdate());
            $this->assertSame($sqlite ? null : $precision, $column->getDatetimePrecision());

            $disable = new Plan();
            $disable->alter($tableName)->modifyColumn('modified_at', $type, default: $default);
            static::executePlan($disable, $connection);
            $column = $generator->generateSchema(static::getSchemaName())->getTable($tableName)->getColumn('modified_at');
            $this->assertNull($column->getOnUpdate());
            $connection->execute("UPDATE $tableName SET modified_at = ?", [$old]);
            $connection->execute("UPDATE $tableName SET value = 4");
            $this->assertSame($old, $connection->fetchOne("SELECT modified_at FROM $tableName")['modified_at']);
        } finally {
            static::executePlan($clean, $connection);
        }
    }

    public static function currentOnUpdateTypes(): array
    {
        return [['TIMESTAMP', 0], ['DATETIME(6)', 6]];
    }

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
            } catch (Throwable) {
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
        $plan->create(static::getTestTableName(), function (TableOperation $t): void {
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
