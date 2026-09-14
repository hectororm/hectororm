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

namespace Hector\Migration\Tests;

use Hector\Connection\Connection;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;
use Hector\Migration\MigrationRunner;
use Hector\Migration\Provider\ArrayProvider;
use Hector\Migration\Tracker\DbTracker;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

class GeneratedColumnMigrationTest extends TestCase
{
    private const TABLE = 'hector_generated_migration_items';
    private const TRACKING = 'hector_generated_migration_tracking';
    private ?Connection $connection = null;

    public static function databaseModes(): array
    {
        return [
            'SQLite VIRTUAL' => ['sqlite', false],
            'SQLite STORED rebuild' => ['sqlite', true],
            'MySQL/MariaDB VIRTUAL' => ['mysql', false],
            'MySQL/MariaDB STORED' => ['mysql', true],
        ];
    }

    private function connect(string $driver): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required for MySQL/MariaDB migration integration tests');
            }
        }

        $connection = $this->connection = new Connection($dsn);
        if ('sqlite' === $driver) {
            $connection->execute('PRAGMA foreign_keys = ON');
        }
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (quantity INT NOT NULL, price INT NOT NULL)');
        $connection->execute('INSERT INTO ' . self::TABLE . ' (quantity, price) VALUES (2, 3), (4, 5)');

        return $connection;
    }

    private function schema(Connection $connection): Schema
    {
        if ('sqlite' === $connection->getDriverInfo()->getDriver()) {
            return (new Sqlite($connection))->generateSchema('main');
        }

        return (new MySQL($connection))->generateSchema($connection->fetchOne('SELECT DATABASE() AS name')['name']);
    }

    private function runner(Connection $connection, bool $stored, bool $fail = false): MigrationRunner
    {
        $migration = new class($stored, $fail) implements MigrationInterface {
            public function __construct(private bool $stored, private bool $fail)
            {
            }

            public function up(Plan $plan): void
            {
                $plan->alter('hector_generated_migration_items')->addColumn('total', 'INTEGER',
                    generated: new Generated('quantity * price', stored: $this->stored));
                if ($this->fail) {
                    $plan->raw('SELECT * FROM hector_generated_migration_missing');
                }
            }
        };

        $tracker = new DbTracker($connection, self::TRACKING);
        $tracker->getArrayCopy(); // Create the tracking table before the migration transaction.

        return new MigrationRunner(
            new ArrayProvider(['generated' => $migration]),
            $tracker,
            new AutoCompiler($connection),
            $connection,
            $this->schema($connection),
        );
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->rollBack();
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TRACKING);
        }
        parent::tearDown();
    }

    /**
     * @dataProvider databaseModes
     */
    public function testDryRunThenExecuteAndTrackGeneratedColumn(string $driver, bool $stored): void
    {
        $connection = $this->connect($driver);
        $runner = $this->runner($connection, $stored);
        $original = $connection->fetchAll('SELECT * FROM ' . self::TABLE . ' ORDER BY quantity');

        $this->assertSame(['generated'], $runner->up(dryRun: true));
        $this->assertSame(['generated' => false], $runner->getStatus());
        $this->assertSame(['quantity', 'price'], $this->schema($connection)->getTable(self::TABLE)->getColumnsName());
        $this->assertSame($original, $connection->fetchAll('SELECT * FROM ' . self::TABLE . ' ORDER BY quantity'));

        $this->assertSame(['generated'], $runner->up());
        $column = $this->schema($connection)->getTable(self::TABLE)->getColumn('total');
        $this->assertTrue($column->isGenerated());
        $this->assertSame($stored, $column->isGeneratedStored());
        $this->assertSame([6, 20], array_map('intval', array_column(
            $connection->fetchAll('SELECT total FROM ' . self::TABLE . ' ORDER BY quantity'),
            'total',
        )));
        $this->assertSame(['generated'], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        $this->assertFalse($connection->inTransaction());
        $this->assertSame([], $runner->up());

        $connection->execute('UPDATE ' . self::TABLE . ' SET price = 6 WHERE quantity = 2');
        $this->assertSame(12, (int)$connection->fetchOne('SELECT total FROM ' . self::TABLE . ' WHERE quantity = 2')['total']);
    }

    /**
     * @dataProvider databaseModes
     */
    public function testFailureRespectsNativeDdlTransactionsAndIsNotTracked(string $driver, bool $stored): void
    {
        $connection = $this->connect($driver);
        $runner = $this->runner($connection, $stored, fail: true);
        $original = $connection->fetchAll('SELECT quantity, price FROM ' . self::TABLE . ' ORDER BY quantity');

        try {
            $runner->up();
            $this->fail('The invalid SQL following the column addition must fail');
        } catch (MigrationException $exception) {
            $this->assertStringContainsString('hector_generated_migration_missing', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }

        $columns = $this->schema($connection)->getTable(self::TABLE)->getColumnsName();
        $this->assertSame('mysql' === $driver, in_array('total', $columns, true));
        $this->assertSame([], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        $this->assertSame(['generated' => false], $runner->getStatus());
        $this->assertFalse($connection->inTransaction());
        $this->assertSame($original,
            $connection->fetchAll('SELECT quantity, price FROM ' . self::TABLE . ' ORDER BY quantity'));

        if ('sqlite' === $driver) {
            $this->assertSame([], $connection->fetchAll("SELECT name FROM sqlite_master WHERE name LIKE '__htemp_%'"));
        }
    }
}
