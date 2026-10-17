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
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class ConnectionAfterCommitTest extends TestCase
{
    private const TABLE = 'hector_after_commit_test';
    private const DDL_TABLE = 'hector_after_commit_ddl_test';

    private ?Connection $connection = null;
    private array $log = [];

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::DDL_TABLE);
        }
        $this->connection = null;
        $this->log = [];
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

    private function getPdoConnection(PDO $pdo): Connection
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection = Connection::fromPdo($pdo);
        $this->connection->execute('CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)');

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

    private function logger(string $entry): callable
    {
        return function () use ($entry): void {
            $this->log[] = $entry;
        };
    }

    private function counter(Connection $connection): int
    {
        return $this->property($connection, 'transactions');
    }

    private function pending(Connection $connection): array
    {
        return $this->property($connection, 'afterCommitCallbacks');
    }

    private function property(Connection $connection, string $name): mixed
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, $name);
        PHP_VERSION_ID < 80100 && $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($connection);
    }

    private static function busyException(): PDOException
    {
        $exception = new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
        $exception->errorInfo = ['HY000', 5, 'database is locked'];

        return $exception;
    }

    /**
     * @dataProvider driverProvider
     */
    public function testRunsAfterOuterCommit(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $seen = null;

        $connection->transaction(function (Connection $connection) use (&$seen): void {
            $this->insert($connection, 1);
            $connection->afterCommit(function () use ($connection, &$seen): void {
                $seen = [$connection->inTransaction(), $this->ids($connection)];
                $this->log[] = 'committed';
            });

            $this->assertSame([], $this->log);
        });

        $this->assertSame(['committed'], $this->log);
        $this->assertSame([false, [1]], $seen);
        $this->assertSame([], $this->pending($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testDiscardedOnOuterRollback(string $driver): void
    {
        $connection = $this->getConnection($driver);

        try {
            $connection->transaction(function (Connection $connection): void {
                $connection->afterCommit($this->logger('discarded'));

                throw new RuntimeException('Failure');
            });
            $this->fail('Exception expected');
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedCommitPromotesCallbacksInOrder(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->transaction(function (Connection $connection): void {
            $connection->afterCommit($this->logger('outer-1'));
            $connection->transaction(function (Connection $connection): void {
                $connection->afterCommit($this->logger('nested-1'));
                $connection->transaction(function (Connection $connection): void {
                    $connection->afterCommit($this->logger('deep'));
                });
                $connection->afterCommit($this->logger('nested-2'));
            });

            // Releasing a savepoint is not a commit.
            $this->assertSame([], $this->log);
            $connection->afterCommit($this->logger('outer-2'));
        });

        $this->assertSame(['outer-1', 'nested-1', 'deep', 'nested-2', 'outer-2'], $this->log);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNestedRollbackDiscardsOnlyItsCallbacks(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->transaction(function (Connection $connection): void {
            $connection->afterCommit($this->logger('outer-1'));

            try {
                $connection->transaction(function (Connection $connection): void {
                    $connection->afterCommit($this->logger('nested'));
                    $connection->transaction(function (Connection $connection): void {
                        $connection->afterCommit($this->logger('deep'));
                    });

                    throw new RuntimeException('Nested failure');
                });
            } catch (RuntimeException) {
            }

            $connection->afterCommit($this->logger('outer-2'));
        });

        $this->assertSame(['outer-1', 'outer-2'], $this->log);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testManualTransactions(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->beginTransaction();
        $connection->afterCommit($this->logger('outer'));
        $connection->beginTransaction();
        $connection->afterCommit($this->logger('rolled-back'));
        $connection->rollBack(allTransactions: false);
        $connection->beginTransaction();
        $connection->afterCommit($this->logger('released'));
        $connection->commit();

        $this->assertSame([], $this->log);

        $connection->commit();

        $this->assertSame(['outer', 'released'], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testManualRollbackAllDiscardsEveryLevel(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $connection->beginTransaction();
        $connection->afterCommit($this->logger('outer'));
        $connection->beginTransaction();
        $connection->afterCommit($this->logger('nested'));
        $connection->rollBack(allTransactions: true);

        $this->assertSame([], $this->pending($connection));

        $connection->transaction(fn() => null);
        $this->assertSame([], $this->log);
    }

    public function testFailedCommitDiscardsCallbacks(): void
    {
        $connection = $this->getConnection('sqlite');
        $connection->execute('PRAGMA foreign_keys = ON');
        $connection->execute('CREATE TABLE parent (id INTEGER NOT NULL PRIMARY KEY)');
        $connection->execute(
            'CREATE TABLE child (parent_id INTEGER NOT NULL ' .
            'REFERENCES parent (id) DEFERRABLE INITIALLY DEFERRED)'
        );

        try {
            $connection->transaction(function (Connection $connection): void {
                $connection->execute('INSERT INTO child (parent_id) VALUES (1)');
                $connection->afterCommit($this->logger('discarded'));
            });
            $this->fail('Exception expected');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('FOREIGN KEY', $exception->getMessage());
        }

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
        $this->assertFalse($connection->inTransaction());
    }

    public function testRetryDiscardsCallbacksOfFailedAttempts(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;

        $connection->transaction(function (Connection $connection) use (&$calls): void {
            $calls++;
            $connection->afterCommit($this->logger('attempt-' . $calls));
            if ($calls < 3) {
                throw self::busyException();
            }
        }, attempts: 3);

        $this->assertSame(3, $calls);
        $this->assertSame(['attempt-3'], $this->log);
    }

    public function testCallbackFailureNeverReplaysCommittedWork(): void
    {
        $connection = $this->getConnection('sqlite');
        $calls = 0;
        $failure = self::busyException();

        try {
            $connection->transaction(function (Connection $connection) use (&$calls, $failure): void {
                $calls++;
                $this->insert($connection, $calls);
                $connection->afterCommit(function () use ($failure): void {
                    // A retryable error must not trigger a retry once committed.
                    throw $failure;
                });
            }, attempts: 3);
            $this->fail('Exception expected');
        } catch (PDOException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame(1, $calls);
        $this->assertSame([1], $this->ids($connection));
        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
    }

    public function testCallbackFailureDiscardsRemainingCallbacks(): void
    {
        $connection = $this->getConnection('sqlite');
        $failure = new RuntimeException('Callback failure');

        try {
            $connection->transaction(function (Connection $connection) use ($failure): void {
                $connection->afterCommit($this->logger('first'));
                $connection->afterCommit(function () use ($failure): void {
                    throw $failure;
                });
                $connection->afterCommit($this->logger('skipped'));
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame(['first'], $this->log);
        $this->assertSame([], $this->pending($connection));

        // Nothing stale is replayed by the next commit.
        $connection->transaction(function (Connection $connection): void {
            $connection->afterCommit($this->logger('next'));
        });
        $this->assertSame(['first', 'next'], $this->log);
    }

    public function testManualCommitCallbackFailureKeepsCommit(): void
    {
        $connection = $this->getConnection('sqlite');
        $failure = new RuntimeException('Callback failure');

        $connection->beginTransaction();
        $this->insert($connection, 1);
        $connection->afterCommit(function () use ($failure): void {
            throw $failure;
        });

        try {
            $connection->commit();
            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertFalse($connection->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $this->assertSame([1], $this->ids($connection));
    }

    public function testRunsImmediatelyWithoutTransaction(): void
    {
        $connection = $this->getConnection('sqlite');

        $connection->afterCommit($this->logger('immediate'));

        $this->assertSame(['immediate'], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    public function testCallbackCanRegisterCallbacksAndOpenTransactions(): void
    {
        $connection = $this->getConnection('sqlite');

        $connection->transaction(function (Connection $connection): void {
            $connection->afterCommit(function () use ($connection): void {
                $this->log[] = 'first';
                $connection->afterCommit($this->logger('immediate'));
                $connection->transaction(function (Connection $connection): void {
                    $this->insert($connection, 1);
                    $connection->afterCommit($this->logger('inner-commit'));
                });
            });
            $connection->afterCommit($this->logger('second'));
        });

        $this->assertSame(['first', 'immediate', 'inner-commit', 'second'], $this->log);
        $this->assertSame([1], $this->ids($connection));
        $this->assertSame([], $this->pending($connection));
    }

    public function testRejectsExternalPdoTransaction(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = $this->getPdoConnection($pdo);

        $pdo->beginTransaction();

        try {
            $connection->afterCommit($this->logger('outside-hector'));
            $this->fail('Exception expected');
        } catch (LogicException) {
        }

        try {
            $connection->transaction(function (Connection $connection): void {
                $connection->transaction(function (Connection $connection): void {
                    $connection->afterCommit($this->logger('inside-hector-levels'));
                });
            });
            $this->fail('Exception expected');
        } catch (LogicException) {
        }

        $this->assertTrue($pdo->inTransaction());
        $this->assertSame(0, $this->counter($connection));
        $pdo->commit();

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    public function testUnobservedCommitDiscardsCallbacksOnCommit(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = $this->getPdoConnection($pdo);

        // Simulate an implicit COMMIT behind Hector's back.
        $connection->beginTransaction();
        $connection->afterCommit($this->logger('unobserved'));
        $pdo->commit();
        $connection->commit();

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    public function testUnobservedCommitDiscardsCallbacksOnRollBack(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = $this->getPdoConnection($pdo);

        $connection->beginTransaction();
        $connection->afterCommit($this->logger('unobserved'));
        $pdo->commit();
        $connection->rollBack(allTransactions: false);

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
    }

    public function testUnobservedCommitDiscardsCallbacksOnNextBegin(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = $this->getPdoConnection($pdo);

        $connection->beginTransaction();
        $connection->afterCommit($this->logger('unobserved'));
        $pdo->commit();

        $connection->transaction(function (Connection $connection): void {
            $connection->afterCommit($this->logger('observed'));
        });

        $this->assertSame(['observed'], $this->log);
    }

    public function testDdlImplicitCommitDiscardsCallbacks(): void
    {
        $connection = $this->getConnection('mysql');

        $connection->transaction(function (Connection $connection): void {
            $this->insert($connection, 1);
            $connection->afterCommit($this->logger('before-ddl'));
            $connection->execute('CREATE TABLE ' . self::DDL_TABLE . ' (id INTEGER NOT NULL PRIMARY KEY)');
        });

        $this->assertSame([], $this->log);
        $this->assertSame([], $this->pending($connection));
        $this->assertSame([1], $this->ids($connection));
    }

    public function testRollbackFailureStillDiscardsCallbacks(): void
    {
        $connection = $this->getConnection('sqlite');
        $failure = new RuntimeException('Original');

        $connection->beginTransaction();
        $connection->afterCommit($this->logger('outer'));

        try {
            $connection->transaction(function (Connection $connection) use ($failure): void {
                $connection->afterCommit($this->logger('nested'));
                // Make ROLLBACK TO SAVEPOINT fail.
                $connection->execute('RELEASE SAVEPOINT hector_tx_2');

                throw $failure;
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame(1, $this->counter($connection));
        $connection->commit();

        $this->assertSame(['outer'], $this->log);
    }
}
