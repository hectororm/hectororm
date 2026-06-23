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
