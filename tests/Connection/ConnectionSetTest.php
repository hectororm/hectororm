<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Connection\Tests;

use Hector\Connection\Connection;
use Hector\Connection\ConnectionSet;
use Hector\Connection\Exception\ConnectionException;
use Hector\Connection\Exception\NotFoundException;
use Hector\Connection\Log\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConnectionSetTest extends TestCase
{
    public function testConstruct(): void
    {
        $connectionSet = new ConnectionSet(
            new Connection('sqlite::memory:'),
            new Connection('sqlite::memory:', name: 'connection1'),
        );

        $this->assertCount(2, $connectionSet);
    }

    public function testConstructEmpty(): void
    {
        $connectionSet = new ConnectionSet();

        $this->assertCount(0, $connectionSet);
    }

    public function testGetConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $defaultConnection = new Connection('sqlite::memory:'),
            $secondConnection = new Connection('sqlite::memory:', name: 'connection'),
        );

        $this->assertSame($defaultConnection, $connectionSet->getConnection());
        $this->assertSame($defaultConnection, $connectionSet->getConnection(Connection::DEFAULT_NAME));
        $this->assertSame($secondConnection, $connectionSet->getConnection('connection'));
    }

    public function testGetConnection_notFound(): void
    {
        $this->expectException(NotFoundException::class);

        $connectionSet = new ConnectionSet();
        $connectionSet->getConnection();
    }

    public function testGetIterator(): void
    {
        $connectionSet = new ConnectionSet(
            $defaultConnection = new Connection('sqlite::memory:'),
            $secondConnection = new Connection('sqlite::memory:', name: 'connection'),
        );

        $this->assertIsIterable($connectionSet);
        $this->assertCount(2, $connectionSet->getIterator());
    }

    public function testHasConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $defaultConnection = new Connection('sqlite::memory:'),
            $secondConnection = new Connection('sqlite::memory:', name: 'connection'),
        );

        $this->assertTrue($connectionSet->hasConnection());
        $this->assertTrue($connectionSet->hasConnection(Connection::DEFAULT_NAME));
        $this->assertTrue($connectionSet->hasConnection('connection'));
        $this->assertFalse($connectionSet->hasConnection('fake'));
    }

    public function testAddConnection(): void
    {
        $connectionSet = new ConnectionSet();

        $this->assertCount(0, $connectionSet);

        $connectionSet->addConnection($connection = new Connection('sqlite:memory:'));

        $this->assertCount(1, $connectionSet);
        $this->assertSame($connection, $connectionSet->getConnection());
    }

    public function testAddConnection_sameObjectThrows(): void
    {
        $connectionSet = new ConnectionSet();
        $connectionSet->addConnection($connection = new Connection('sqlite:memory:'));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('already registered');

        $connectionSet->addConnection($connection);
    }

    public function testAddConnection_sameNameNotTheSameObjectThrows(): void
    {
        $connectionSet = new ConnectionSet();
        $connectionSet->addConnection(new Connection('sqlite:memory:'));

        // A different connection reusing an already-registered name is a configuration error:
        // it must throw rather than silently overwrite (and lose) the first one.
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('already registered');

        $connectionSet->addConnection(new Connection('sqlite:memory:'));
    }

    public function testTransactionSpansEveryConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $connection1 = new Connection('sqlite::memory:'),
            $connection2 = new Connection('sqlite::memory:', name: 'connection'),
        );

        $connectionSet->beginTransaction();
        $this->assertTrue($connection1->inTransaction());
        $this->assertTrue($connection2->inTransaction());

        $connectionSet->commit();
        $this->assertFalse($connection1->inTransaction());
        $this->assertFalse($connection2->inTransaction());
    }

    public function testRollBackSpansEveryConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $connection1 = new Connection('sqlite::memory:'),
            $connection2 = new Connection('sqlite::memory:', name: 'connection'),
        );

        $connectionSet->beginTransaction();
        $connectionSet->rollBack();

        $this->assertFalse($connection1->inTransaction());
        $this->assertFalse($connection2->inTransaction());
    }

    public function testTransactionCommitsEveryConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $connection1 = new Connection('sqlite::memory:'),
            $connection2 = new Connection('sqlite::memory:', name: 'connection'),
        );
        $connection1->execute('CREATE TABLE foo (id INTEGER)');
        $connection2->execute('CREATE TABLE foo (id INTEGER)');

        $result = $connectionSet->transaction(function (ConnectionSet $set) use ($connectionSet): string {
            $this->assertSame($connectionSet, $set);
            foreach ($set as $connection) {
                $this->assertTrue($connection->inTransaction());
                $connection->execute('INSERT INTO foo (id) VALUES (1)');
            }

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertFalse($connection1->inTransaction());
        $this->assertFalse($connection2->inTransaction());
        $this->assertEquals([1], $connection1->fetchColumn('SELECT id FROM foo'));
        $this->assertEquals([1], $connection2->fetchColumn('SELECT id FROM foo'));
    }

    public function testTransactionRollsBackEveryConnection(): void
    {
        $connectionSet = new ConnectionSet(
            $connection1 = new Connection('sqlite::memory:'),
            $connection2 = new Connection('sqlite::memory:', name: 'connection'),
        );
        $connection1->execute('CREATE TABLE foo (id INTEGER)');
        $connection2->execute('CREATE TABLE foo (id INTEGER)');
        $exception = new RuntimeException('Failure');

        try {
            $connectionSet->transaction(function (ConnectionSet $set) use ($exception): void {
                foreach ($set as $connection) {
                    $connection->execute('INSERT INTO foo (id) VALUES (1)');
                }

                throw $exception;
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertFalse($connection1->inTransaction());
        $this->assertFalse($connection2->inTransaction());
        $this->assertSame([], $connection1->fetchColumn('SELECT id FROM foo'));
        $this->assertSame([], $connection2->fetchColumn('SELECT id FROM foo'));
    }

    public function testTransactionRollsBackOpenedConnectionsWhenBeginFails(): void
    {
        $connectionSet = new ConnectionSet(
            $connection1 = new Connection('sqlite::memory:'),
            new Connection('sqlite:/nonexistent/directory/database.sqlite', name: 'broken'),
        );
        $called = false;

        try {
            $connectionSet->transaction(function () use (&$called): void {
                $called = true;
            });
            $this->fail('Exception expected');
        } catch (ConnectionException) {
        }

        $this->assertFalse($called);
        $this->assertFalse($connection1->inTransaction());
    }

    public function testPartialRollbackPreservesOuterTransactions(): void
    {
        $set = new ConnectionSet(
            new Connection('sqlite::memory:'),
            new Connection('sqlite::memory:', name: 'other'),
        );
        foreach ($set as $connection) {
            $connection->execute('CREATE TABLE foo (id INTEGER)');
        }
        $set->beginTransaction();
        foreach ($set as $connection) {
            $connection->execute('INSERT INTO foo VALUES (1)');
        }
        $set->beginTransaction();
        foreach ($set as $connection) {
            $connection->execute('INSERT INTO foo VALUES (2)');
        }
        $set->rollBack(allTransactions: false);
        foreach ($set as $connection) {
            $this->assertTrue($connection->inTransaction());
        }
        $set->commit();
        foreach ($set as $connection) {
            $this->assertEquals([1], $connection->fetchColumn('SELECT id FROM foo'));
            $this->assertFalse($connection->inTransaction());
        }
    }

    public function testGetLoggers(): void
    {
        $connectionSet = new ConnectionSet();

        $connectionSet->addConnection(
            $connection1 = new Connection(
                'sqlite:memory:',
                name: 'connection1',
                logger: new Logger()
            )
        );
        $connectionSet->addConnection(
            $connection2 = new Connection(
                'sqlite:memory:',
                name: 'connection2',
                logger: new Logger()
            )
        );
        $connectionSet->addConnection(new Connection('sqlite:memory:', name: 'connection3'));

        $this->assertSame(
            [
                $connection1->getLogger(),
                $connection2->getLogger(),
            ],
            iterator_to_array($connectionSet->getLoggers())
        );
    }
}
