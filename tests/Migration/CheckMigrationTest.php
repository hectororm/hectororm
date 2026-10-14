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

use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\CreateTable;
use Closure;
use Hector\Connection\Connection;
use Hector\Migration\Event\MigrationAfterEvent;
use Hector\Migration\Event\MigrationFailedEvent;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationRunner;
use Hector\Migration\Provider\ArrayProvider;
use Hector\Migration\ReversibleMigrationInterface;
use Hector\Migration\Tracker\DbTracker;
use Hector\Migration\Tracker\MigrationTrackerInterface;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PDOException;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class CheckMigrationTest extends TestCase
{
    private const TABLE = 'hector_check_migration_items';
    private const TRACKING = 'hector_check_migration_tracking';
    private ?Connection $connection = null;

    private function connect(string $driver = 'sqlite'): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required');
            }
        }
        $this->connection = new Connection($dsn);
        if (false === $this->connection->getDriverInfo()->getCapabilities()->hasCheckConstraints()) {
            $this->markTestSkipped('The server does not enforce CHECK constraints');
        }

        return $this->connection;
    }

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->rollBack();
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TRACKING);
        }
    }

    private function schema(): Schema
    {
        return 'sqlite' === $this->connection->getDriverInfo()->getDriver()
            ? (new Sqlite($this->connection))->generateSchema('main')
            : (new MySQL($this->connection))->generateSchema($this->connection->fetchOne('SELECT DATABASE() AS db')['db']);
    }

    private function migration(Closure $up, ?Closure $down = null): ReversibleMigrationInterface
    {
        return new class($up, $down) implements ReversibleMigrationInterface {
            public function __construct(private Closure $up, private ?Closure $down)
            {
            }

            public function up(Plan $plan): void
            {
                ($this->up)($plan);
            }

            public function down(Plan $plan): void
            {
                if (null !== $this->down) {
                    ($this->down)($plan);
                }
            }
        };
    }

    private function runner(
        array $migrations,
        ?LoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
        ?MigrationTrackerInterface $tracker = null,
    ): MigrationRunner {
        $tracker ??= new DbTracker($this->connection, self::TRACKING);
        $tracker->getArrayCopy();

        return new MigrationRunner(new ArrayProvider($migrations), $tracker, new AutoCompiler($this->connection),
            $this->connection, $this->schema(), $logger, $events);
    }

    public static function drivers(): array
    {
        return [['sqlite'], ['mysql']];
    }

    /** @dataProvider drivers */
    public function testDryRunThenUpDownWithTracking(string $driver): void
    {
        $connection = $this->connect($driver);
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (1), (NULL)');
        $migration = $this->migration(
            static fn(Plan $plan): AlterTable => $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0'),
            static fn(Plan $plan): AlterTable => $plan->alter(self::TABLE)->dropCheck('positive'),
        );
        $messages = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug')->willReturnCallback(static function ($message) use (&$messages): void {
            $messages[] = $message;
        });
        $runner = $this->runner(['positive' => $migration], $logger);
        $original = $connection->fetchAll('SELECT * FROM ' . self::TABLE);

        self::assertSame(['positive'], $runner->up(dryRun: true));
        self::assertSame($original, $connection->fetchAll('SELECT * FROM ' . self::TABLE));
        self::assertCount(0, iterator_to_array($this->schema()->getTable(self::TABLE)->getChecks()));
        self::assertSame(['positive' => false], $runner->getStatus());
        self::assertStringContainsString('CHECK (price >= 0)', implode("\n", $messages));

        self::assertSame(['positive'], $runner->up());
        self::assertTrue($this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame(['positive'], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        try {
            $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (-1)');
            self::fail('CHECK must reject negative prices');
        } catch (PDOException $exception) {
            self::assertStringContainsString('positive', $exception->getMessage());
        }

        self::assertSame(['positive'], $runner->down(dryRun: true));
        self::assertTrue($this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame(['positive' => true], $runner->getStatus());
        self::assertSame(['positive'], $runner->down());
        self::assertFalse($this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame([], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (-1)');
        self::assertFalse($connection->inTransaction());
    }

    /** @dataProvider drivers */
    public function testCreateCheckMigration(string $driver): void
    {
        $connection = $this->connect($driver);
        $runner = $this->runner(['create' => $this->migration(
            static fn(Plan $plan): CreateTable => $plan->create(self::TABLE)->addColumn('price', 'INT')
                ->addCheck('positive', 'price >= 0'),
            static fn(Plan $plan): Plan => $plan->drop(self::TABLE),
        )]);
        self::assertSame(['create'], $runner->up());
        self::assertTrue($this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame(['create'], $runner->down());
        self::assertFalse($this->schema()->hasTable(self::TABLE));
        self::assertFalse($connection->inTransaction());
    }

    public static function foreignKeyStates(): array
    {
        return [[0, false], [1, false], [0, true], [1, true]];
    }

    /** @dataProvider foreignKeyStates */
    public function testSqliteRebuildPreservesChildrenAndRestoresForeignKeySetting(int $initial, bool $managed): void
    {
        $connection = $this->connect();
        $connection->execute('PRAGMA foreign_keys = ' . $initial);
        $connection->execute('CREATE TABLE parents (id INTEGER PRIMARY KEY, price INT CHECK(price < 100))');
        $connection->execute('CREATE TABLE children (parent_id INT REFERENCES parents(id) ON DELETE CASCADE)');
        $connection->execute('INSERT INTO parents VALUES (1, 10)');
        $connection->execute('INSERT INTO children VALUES (1)');
        $runner = $this->runner(['positive' => $this->migration(
            static function (Plan $plan) use ($managed): void {
                if ($managed) {
                    $plan->add(new DisableForeignKeyChecks());
                }
                $plan->alter('parents')->addCheck('positive', 'price >= 0');
                if ($managed) {
                    $plan->add(new EnableForeignKeyChecks());
                }
            },
            static fn(Plan $plan): AlterTable => $plan->alter('parents')->dropCheck('positive'),
        )]);

        $runner->up(dryRun: true);
        self::assertSame([$initial], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertSame([1], $connection->fetchColumn('SELECT parent_id FROM children'));
        $runner->up();
        self::assertSame([1], $connection->fetchColumn('SELECT parent_id FROM children'));
        self::assertSame([$initial], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertCount(2, iterator_to_array($this->schema()->getTable('parents')->getChecks()));
        $runner->down();
        self::assertSame([1], $connection->fetchColumn('SELECT parent_id FROM children'));
        self::assertSame([$initial], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertCount(1, iterator_to_array($this->schema()->getTable('parents')->getChecks()));
        self::assertSame([], $connection->fetchAll('PRAGMA foreign_key_check'));
        self::assertFalse($connection->inTransaction());
    }

    public static function failures(): iterable
    {
        foreach ([0, 1] as $initial) {
            foreach (['copy', 'later SQL', 'foreign key', 'tracking'] as $failure) {
                yield [$initial, $failure];
            }
        }
    }

    /** @dataProvider failures */
    public function testSqliteFailureRestoresSchemaDataSettingsAndTracking(int $initial, string $failure): void
    {
        $connection = $this->connect();
        $connection->execute('PRAGMA foreign_keys = ' . $initial);
        $connection->execute('CREATE TABLE parents (id INTEGER PRIMARY KEY, price INT CHECK(price < 100))');
        $connection->execute('CREATE TABLE children (parent_id INT REFERENCES parents(id) ON DELETE CASCADE)');
        $connection->execute('INSERT INTO parents VALUES (1, ' . ('copy' === $failure ? '-1' : '10') . ')');
        $connection->execute('INSERT INTO children VALUES (1)');
        $connection->execute('CREATE INDEX idx_price ON parents(price)');
        $connection->execute('CREATE TRIGGER observe AFTER INSERT ON parents BEGIN SELECT NEW.price; END');
        $tracker = new DbTracker($connection, self::TRACKING);
        $tracker->getArrayCopy();
        if ('tracking' === $failure) {
            $tracker = $this->createMock(MigrationTrackerInterface::class);
            $tracker->method('getArrayCopy')->willReturn([]);
            $tracker->method('isApplied')->willReturn(false);
            $tracker->method('markApplied')->willThrowException(new RuntimeException('tracking failed'));
        }
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;

            return $event;
        });
        $runner = $this->runner(['positive' => $this->migration(static function (Plan $plan) use ($failure): void {
            $plan->alter('parents')->addCheck('positive', 'price >= 0');
            if ('later SQL' === $failure) {
                $plan->raw('SELECT * FROM missing_table');
            }
            if ('foreign key' === $failure) {
                $plan->raw('INSERT INTO children VALUES (999)');
            }
        })], events: $dispatcher, tracker: $tracker);
        $originalSchema = $connection->fetchAll('SELECT name, sql FROM sqlite_master ORDER BY name');
        $originalRows = $connection->fetchAll('SELECT * FROM parents');

        try {
            $runner->up();
            self::fail('Migration should fail');
        } catch (MigrationException $exception) {
            self::assertStringContainsString(match ($failure) {
                'copy' => 'positive',
                'later SQL' => 'missing_table',
                'foreign key' => 'foreign key check failed',
                'tracking' => 'tracking failed',
            }, $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }

        self::assertSame($originalSchema, $connection->fetchAll('SELECT name, sql FROM sqlite_master ORDER BY name'));
        self::assertSame($originalRows, $connection->fetchAll('SELECT * FROM parents'));
        self::assertSame([1], $connection->fetchColumn('SELECT parent_id FROM children'));
        self::assertSame([$initial], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertSame([], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        self::assertSame(['positive' => false], $runner->getStatus());
        self::assertFalse($connection->inTransaction());
        self::assertCount(1, array_filter($events, static fn(object $event): bool => $event instanceof MigrationFailedEvent));
        self::assertCount(0, array_filter($events, static fn(object $event): bool => $event instanceof MigrationAfterEvent));
    }

    public function testRebuildRefusesCallerOwnedTransactionWithoutRollingItBack(): void
    {
        $connection = $this->connect();
        $connection->execute('PRAGMA foreign_keys = ON');
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $runner = $this->runner(['positive' => $this->migration(
            static fn(Plan $plan): AlterTable => $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0'),
        )]);
        $connection->beginTransaction();
        $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (10)');
        try {
            $runner->up();
            self::fail('Rebuild should refuse a caller-owned transaction');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('require their own transaction', $exception->getMessage());
        }
        self::assertTrue($connection->inTransaction());
        self::assertSame([10], $connection->fetchColumn('SELECT price FROM ' . self::TABLE));
        self::assertSame([1], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertSame(['positive' => false], $runner->getStatus());
        $connection->rollBack();
    }

    public function testSeparateMigrationsRefreshSqliteMetadata(): void
    {
        $this->connect()->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $runner = $this->runner([
            'positive' => $this->migration(static fn(Plan $plan): AlterTable =>
                $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0')),
            'upper' => $this->migration(static fn(Plan $plan): AlterTable =>
                $plan->alter(self::TABLE)->addCheck('upper_price', 'price < 100')),
        ]);
        self::assertSame(['positive', 'upper'], $runner->up());
        $table = $this->schema()->getTable(self::TABLE);
        self::assertTrue($table->hasCheck('positive'));
        self::assertTrue($table->hasCheck('upper_price'));
    }

    public function testRepeatedRebuildInOnePlanFailsBeforeExecutingAnySql(): void
    {
        $connection = $this->connect();
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (10)');
        $runner = $this->runner(['two_checks' => $this->migration(static function (Plan $plan): void {
            $plan->raw('DELETE FROM ' . self::TABLE);
            $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0');
            $plan->alter(self::TABLE)->addCheck('upper_price', 'price < 100');
        })]);
        try {
            $runner->up();
            self::fail('Stale metadata must not silently erase the first constraint');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('stale table metadata', $exception->getMessage());
        }
        self::assertSame([10], $connection->fetchColumn('SELECT price FROM ' . self::TABLE));
        self::assertCount(0, iterator_to_array($this->schema()->getTable(self::TABLE)->getChecks()));
        self::assertSame(['two_checks' => false], $runner->getStatus());
    }

    public function testDisabledCheckEnforcementFailsClearly(): void
    {
        $connection = $this->connect();
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $connection->execute('PRAGMA ignore_check_constraints = ON');
        $runner = $this->runner(['positive' => $this->migration(
            static fn(Plan $plan): AlterTable => $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0'),
        )]);
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('CHECK enforcement is disabled');
        $runner->up();
    }

    public function testUnsupportedCompilationDoesNotExecuteEarlierDdl(): void
    {
        $connection = $this->connect('mysql');
        $tracker = new DbTracker($connection, self::TRACKING);
        $tracker->getArrayCopy();
        $migration = $this->migration(static function (Plan $plan): void {
            $plan->create(self::TABLE)->addColumn('price', 'INT');
            $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0');
        });
        // Offline-style compiler without explicit enforcement capabilities must fail.
        $runner = new MigrationRunner(new ArrayProvider(['positive' => $migration]), $tracker,
            new MySQLCompiler(), $connection);
        try {
            $runner->up();
            self::fail('Missing capabilities must reject CHECK compilation');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('capabilities confirming enforcement', $exception->getMessage());
        }
        self::assertFalse($this->schema()->hasTable(self::TABLE));
        self::assertSame([], $tracker->getArrayCopy());
        self::assertFalse($connection->inTransaction());
    }

    /** @dataProvider drivers */
    public function testLateFailureHonorsNativeDdlTransactionSupport(string $driver): void
    {
        $connection = $this->connect($driver);
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $connection->execute('INSERT INTO ' . self::TABLE . ' VALUES (10)');
        $runner = $this->runner(['positive' => $this->migration(static function (Plan $plan): void {
            $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0');
            $plan->raw('SELECT * FROM hector_check_missing_table');
        })]);
        try {
            $runner->up();
            self::fail('Later statement should fail');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('hector_check_missing_table', $exception->getMessage());
        }

        self::assertSame('mysql' === $driver, $this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame([10], array_map('intval', $connection->fetchColumn('SELECT price FROM ' . self::TABLE)));
        self::assertSame([], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        self::assertSame(['positive' => false], $runner->getStatus());
        self::assertFalse($connection->inTransaction());
    }

    public function testFailedDownKeepsAppliedTrackingAndOriginalCheck(): void
    {
        $connection = $this->connect();
        $connection->execute('PRAGMA foreign_keys = ON');
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (price INT)');
        $runner = $this->runner(['positive' => $this->migration(
            static fn(Plan $plan): AlterTable => $plan->alter(self::TABLE)->addCheck('positive', 'price >= 0'),
            static function (Plan $plan): void {
                $plan->alter(self::TABLE)->dropCheck('positive');
                $plan->raw('SELECT * FROM missing_table');
            },
        )]);
        $runner->up();
        try {
            $runner->down();
            self::fail('Down should fail');
        } catch (MigrationException $exception) {
            self::assertStringContainsString('missing_table', $exception->getMessage());
        }
        self::assertTrue($this->schema()->getTable(self::TABLE)->hasCheck('positive'));
        self::assertSame(['positive'], (new DbTracker($connection, self::TRACKING))->getArrayCopy());
        self::assertSame(['positive' => true], $runner->getStatus());
        self::assertSame([1], $connection->fetchColumn('PRAGMA foreign_keys'));
        self::assertFalse($connection->inTransaction());
    }
}
