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

namespace Hector\Connection\Tests;

use Hector\Connection\Connection;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class ConnectionTransactionTest extends TestCase
{
    private const TABLE = 'hector_transaction_test';
    private const DDL_TABLE = 'hector_transaction_ddl_test';

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::DDL_TABLE);
        }
        $this->connection = null;
    }

    public static function driverProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    private function getConnection(string $driver): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required');
            }
        }

        $this->connection = new Connection($dsn);
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::DDL_TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)' .
            ('mysql' === $driver ? ' ENGINE=InnoDB' : '')
        );

        return $this->connection;
    }

    private function insert(Connection $connection, int $id): void
    {
        $connection->execute('INSERT INTO ' . self::TABLE . ' (id) VALUES (?)', [$id]);
    }

    private function ids(Connection $connection): array
    {
        return array_map(
            'intval',
            $connection->fetchColumn('SELECT id FROM ' . self::TABLE . ' ORDER BY id')
        );
    }

    private function counter(Connection $connection): int
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        PHP_VERSION_ID < 80100 && $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($connection);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testCommitsAndReturnsResult(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $result = $connection->transaction(function (Connection $work) use ($connection): string {
            $this->assertSame($connection, $work);
            $this->assertTrue($work->inTransaction());
            $this->insert($work, 1);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testRollsBackAndRethrowsOriginalException(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $exception = new RuntimeException('Failure');

        try {
            $connection->transaction(function (Connection $connection) use ($exception): void {
                $this->insert($connection, 1);

                throw $exception;
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedSuccessCommitsEverything(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->transaction(function (Connection $connection): void {
            $this->insert($connection, 1);
            $connection->transaction(function (Connection $connection): void {
                $this->assertSame(2, $this->counter($connection));
                $this->insert($connection, 2);
            });
            $this->assertSame(1, $this->counter($connection));
        });

        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1, 2], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedFailureOnlyRollsBackSavepoint(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->transaction(function (Connection $connection): void {
            $this->insert($connection, 1);

            try {
                $connection->transaction(function (Connection $connection): void {
                    $this->insert($connection, 2);

                    throw new RuntimeException('Inner failure');
                });
            } catch (RuntimeException) {
            }

            $this->assertTrue($connection->inTransaction());
            $this->assertSame(1, $this->counter($connection));
            $this->insert($connection, 3);
        });

        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1, 3], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedFailurePropagatedRollsBackEverything(string $driver): void
    {
        $connection = $this->getConnection($driver);

        try {
            $connection->transaction(function (Connection $connection): void {
                $this->insert($connection, 1);
                $connection->transaction(function (Connection $connection): void {
                    $this->insert($connection, 2);

                    throw new RuntimeException('Inner failure');
                });
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('Inner failure', $exception->getMessage());
        }

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testInsideManualTransaction(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->beginTransaction();
        $this->insert($connection, 1);
        $connection->transaction(fn(Connection $connection) => $this->insert($connection, 2));
        $this->assertSame(1, $this->counter($connection));
        $connection->commit();

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1, 2], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedDdlImplicitCommit(string $driver): void
    {
        $connection = $this->getConnection($driver);
        if (true === $connection->getDriverInfo()->getCapabilities()->hasTransactionalDdl()) {
            $this->markTestSkipped('DDL is transactional on this driver');
        }

        $connection->transaction(function (Connection $connection): void {
            $this->insert($connection, 1);
            $connection->transaction(function (Connection $connection): void {
                $connection->execute('CREATE TABLE ' . self::DDL_TABLE . ' (id INTEGER NOT NULL)');
            });
        });

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1], $this->ids($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedDdlImplicitCommitThenFailure(string $driver): void
    {
        $connection = $this->getConnection($driver);
        if (true === $connection->getDriverInfo()->getCapabilities()->hasTransactionalDdl()) {
            $this->markTestSkipped('DDL is transactional on this driver');
        }

        try {
            $connection->transaction(function (Connection $connection): void {
                $connection->transaction(function (Connection $connection): void {
                    $connection->execute('CREATE TABLE ' . self::DDL_TABLE . ' (id INTEGER NOT NULL)');

                    throw new RuntimeException('Failure after DDL');
                });
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure after DDL', $exception->getMessage());
        }

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
    }

    public function testUsesSavepointInsideExternalPdoTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection = Connection::fromPdo($pdo);
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)');

        $pdo->beginTransaction();
        $this->insert($connection, 1);

        try {
            $connection->transaction(function (Connection $connection): void {
                $this->assertSame(1, $this->counter($connection));
                $this->insert($connection, 2);

                throw new RuntimeException('Failure');
            });
        } catch (RuntimeException) {
        }

        $this->assertTrue($pdo->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $pdo->commit();

        $this->assertSame([1], $this->ids($connection));
    }

    public function testStaleCounterStillOpensRealTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection = Connection::fromPdo($pdo);
        $connection->execute('CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)');

        // Simulate an implicit COMMIT leaving the counter behind PDO.
        $connection->beginTransaction();
        $pdo->commit();

        try {
            $connection->transaction(function (Connection $connection): void {
                $this->assertTrue($connection->inTransaction());
                $this->insert($connection, 1);

                throw new RuntimeException('Failure');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse($pdo->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([], $this->ids($connection));
    }

    public function testTransactionClosedInsideNestedWork(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection = Connection::fromPdo($pdo);

        $connection->beginTransaction();
        $connection->transaction(fn() => $connection->rollBack(allTransactions: true));

        $this->assertFalse($pdo->inTransaction());
        $this->assertSame(0, $this->counter($connection));

        // Outer commit must stay a no-op.
        $connection->commit();
        $this->assertSame(0, $this->counter($connection));
    }

    public function testRollbackFailureKeepsOriginalException(): void
    {
        $connection = new Connection('sqlite::memory:');
        $exception = new RuntimeException('Original');

        $connection->beginTransaction();

        try {
            $connection->transaction(function (Connection $connection) use ($exception): void {
                // Make ROLLBACK TO SAVEPOINT fail.
                $connection->execute('RELEASE SAVEPOINT hector_tx_2');

                throw $exception;
            });
            $this->fail('Exception expected');
        } catch (Throwable $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertTrue($connection->inTransaction());
        $this->assertSame(1, $this->counter($connection));
        $connection->rollBack();
    }

    public function testCommitFailureRollsBack(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->execute('PRAGMA foreign_keys = ON');
        $connection->execute('CREATE TABLE parent (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->execute(
            'CREATE TABLE child (parent_id INTEGER NOT NULL ' .
            'REFERENCES parent (id) DEFERRABLE INITIALLY DEFERRED)'
        );

        try {
            $connection->transaction(
                fn(Connection $connection): int => $connection->execute('INSERT INTO child (parent_id) VALUES (1)')
            );
            $this->fail('Exception expected');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('FOREIGN KEY', $exception->getMessage());
        }

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([], $connection->fetchColumn('SELECT parent_id FROM child'));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testManualRollbackOnlyDiscardsCurrentLevel(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $connection->beginTransaction();
        $this->insert($connection, 1);
        $connection->beginTransaction();
        $this->insert($connection, 2);
        $connection->beginTransaction();
        $this->insert($connection, 3);
        $connection->rollBack(allTransactions: false);
        $this->assertSame(2, $this->counter($connection));
        $this->assertSame([1, 2], $this->ids($connection));
        $connection->commit();
        $connection->rollBack(allTransactions: false);
        $this->assertSame([], $this->ids($connection));
        $this->assertSame(0, $this->counter($connection));
        $this->assertFalse($connection->inTransaction());
    }

    /**
     * @dataProvider driverProvider
     */
    public function testManualRollbackAllDiscardsEveryLevel(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $connection->beginTransaction();
        $this->insert($connection, 1);
        $connection->beginTransaction();
        $this->insert($connection, 2);
        $connection->rollBack(allTransactions: true);
        $this->assertSame([], $this->ids($connection));
        $this->assertSame(0, $this->counter($connection));
        $this->assertFalse($connection->inTransaction());
    }

    /**
     * @dataProvider driverProvider
     */
    public function testManualLevelsPreserveExternalTransaction(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $pdo = $connection->getPdo();
        $pdo->beginTransaction();
        $this->insert($connection, 1);
        $connection->beginTransaction();
        $this->insert($connection, 2);
        $connection->beginTransaction();
        $this->insert($connection, 3);
        $connection->rollBack(allTransactions: true);
        $this->assertTrue($pdo->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1], $this->ids($connection));

        $connection->beginTransaction();
        $this->insert($connection, 4);
        $connection->commit();
        $this->assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        $this->assertSame([], $this->ids($connection));
    }

    public function testImplicitNestedRollbackIsDeprecatedAndStillRollsBackAll(): void
    {
        $connection = $this->getConnection('sqlite');
        $connection->beginTransaction();
        $this->insert($connection, 1);
        $connection->beginTransaction();
        $this->insert($connection, 2);
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });
        try {
            $connection->rollBack();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertSame(E_USER_DEPRECATED, $warnings[0][0]);
        $this->assertStringContainsString('allTransactions: true', $warnings[0][1]);
        $this->assertSame([], $this->ids($connection));
        $this->assertSame(0, $this->counter($connection));
    }

    public function testManualBeginRecoversFromImplicitCommit(): void
    {
        $connection = $this->getConnection('sqlite');
        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->getPdo()->commit();
        $connection->beginTransaction();
        $this->assertTrue($connection->inTransaction());
        $this->assertSame(1, $this->counter($connection));
        $this->insert($connection, 1);
        $connection->rollBack();
        $this->assertSame([], $this->ids($connection));
    }

    public function testFailedSavepointDoesNotIncrementCounter(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(true);
        $failure = new RuntimeException('Cannot create savepoint');
        $pdo->method('prepare')->willThrowException($failure);
        $connection = Connection::fromPdo($pdo);
        try {
            $connection->beginTransaction();
            $this->fail('Savepoint creation must fail');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }
        $this->assertSame(0, $this->counter($connection));
    }

    private static function busyException(): PDOException
    {
        $exception = new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
        $exception->errorInfo = ['HY000', 5, 'database is locked'];

        return $exception;
    }

    public function testRetriesWholeTransactionOnRetryableFailure(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;

        $result = $connection->transaction(function (Connection $connection) use (&$calls): int {
            $calls++;
            $this->insert($connection, $calls);
            if ($calls < 3) {
                throw self::busyException();
            }

            return $calls;
        }, attempts: 3);

        $this->assertSame(3, $result);
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([3], $this->ids($connection));
    }

    public function testRetriesWrappedRetryableFailure(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;

        $connection->transaction(function () use (&$calls): void {
            if (1 === ++$calls) {
                throw new RuntimeException('Wrapped', 0, self::busyException());
            }
        }, attempts: 2);

        $this->assertSame(2, $calls);
    }

    public function testRethrowsLastFailureWhenAttemptsAreExhausted(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;
        $last = null;

        try {
            $connection->transaction(function () use (&$calls, &$last): void {
                $calls++;
                throw $last = self::busyException();
            }, attempts: 2);
            $this->fail('Exception expected');
        } catch (PDOException $exception) {
            $this->assertSame($last, $exception);
        }

        $this->assertSame(2, $calls);
        $this->assertSame(0, $this->counter($connection));
    }

    public function testDoesNotRetryNonRetryableFailure(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;

        try {
            $connection->transaction(function () use (&$calls): void {
                $calls++;
                throw new RuntimeException('Failure');
            }, attempts: 3);
            $this->fail('Exception expected');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $calls);
    }

    public function testDoesNotRetryNestedTransaction(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;

        $connection->transaction(function (Connection $connection) use (&$calls): void {
            try {
                $connection->transaction(function () use (&$calls): void {
                    $calls++;
                    throw self::busyException();
                }, attempts: 3);
            } catch (PDOException) {
            }
        });

        $this->assertSame(1, $calls);
    }

    public function testRejectsInvalidAttempts(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->expectException(InvalidArgumentException::class);

        $connection->transaction(fn() => $this->fail('Work must not be called'), attempts: 0);
    }

    public function testRetriesRealSqliteBusy(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'hector');

        try {
            $holder = new Connection('sqlite:' . $file);
            $holder->execute('CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)');
            // Busy timeout disabled: a locked database fails immediately with SQLITE_BUSY.
            $connection = new Connection('sqlite:' . $file, options: [PDO::ATTR_TIMEOUT => 0]);

            $holder->beginTransaction();
            $this->insert($holder, 1);

            $calls = 0;
            $connection->transaction(function (Connection $connection) use (&$calls, $holder): void {
                if (2 === ++$calls) {
                    $holder->rollBack();
                }
                $this->insert($connection, 2);
            }, attempts: 2);

            $this->assertSame(2, $calls);
            $this->assertSame([2], $this->ids($connection));
        } finally {
            unset($holder, $connection);
            @unlink($file);
        }
    }

    /**
     * @dataProvider driverProvider
     */
    public function testRetriesRealLockWaitTimeout(string $driver): void
    {
        if ('sqlite' === $driver) {
            $this->markTestSkipped('Lock wait timeout is specific to MySQL/MariaDB');
        }

        $connection = $this->getConnection($driver);
        $this->insert($connection, 1);
        $holder = new Connection(getenv('MYSQL_DSN'));
        $holder->beginTransaction();
        $holder->execute('UPDATE ' . self::TABLE . ' SET id = 1 WHERE id = 1');

        try {
            $connection->execute('SET SESSION innodb_lock_wait_timeout = 1');
            $calls = 0;
            $connection->transaction(function (Connection $connection) use (&$calls, $holder): void {
                if (2 === ++$calls) {
                    $holder->rollBack();
                }
                $connection->execute('UPDATE ' . self::TABLE . ' SET id = 2 WHERE id = 1');
            }, attempts: 2);

            $this->assertSame(2, $calls);
            $this->assertSame([2], $this->ids($connection));
        } finally {
            $holder->rollBack();
        }
    }

    public function testReadsUseWritePdo(): void
    {
        $connection = new Connection('sqlite::memory:', readDsn: 'sqlite::memory:');

        $connection->transaction(function (Connection $connection): void {
            $this->assertSame($connection->getPdo(), $connection->getReadPdo());
        });

        $this->assertNotSame($connection->getPdo(), $connection->getReadPdo());
    }
}
