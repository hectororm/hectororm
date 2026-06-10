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

declare(strict_types=1);

namespace Hector\Connection;

use ArrayIterator;
use Countable;
use Generator;
use Hector\Connection\Exception\ConnectionException;
use Hector\Connection\Exception\NotFoundException;
use Hector\Connection\Log\Logger;
use IteratorAggregate;

class ConnectionSet implements Countable, IteratorAggregate
{
    /** @var Connection[] */
    protected array $connections = [];

    /**
     * ConnectionSet constructor.
     *
     * @param Connection ...$connections
     */
    public function __construct(Connection ...$connections)
    {
        array_walk($connections, fn(Connection $connection) => $this->addConnection($connection));
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->connections);
    }

    /**
     * Add connection.
     *
     * @param Connection $connection
     *
     * @throws ConnectionException If a connection with the same name is already registered
     */
    public function addConnection(Connection $connection): void
    {
        $name = $connection->getName();

        // Reject a duplicate name rather than silently overwriting (and losing) the previously
        // registered connection, which is a configuration error.
        if (true === isset($this->connections[$name])) {
            throw new ConnectionException(sprintf('A connection named "%s" is already registered', $name));
        }

        $this->connections[$name] = $connection;
    }

    /**
     * Has connection?
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasConnection(string $name = Connection::DEFAULT_NAME): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * Get connection.
     *
     * @param string $name
     *
     * @return Connection
     * @throws NotFoundException
     */
    public function getConnection(string $name = Connection::DEFAULT_NAME): Connection
    {
        return $this->connections[$name] ?? throw new NotFoundException(sprintf('Connection "%s" not found', $name));
    }

    /**
     * Begin a transaction on every connection of the set.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        foreach ($this->connections as $connection) {
            $connection->beginTransaction();
        }
    }

    /**
     * Commit the transaction on every connection of the set.
     *
     * @return void
     */
    public function commit(): void
    {
        foreach ($this->connections as $connection) {
            $connection->commit();
        }
    }

    /**
     * Roll back the transaction on every connection of the set.
     *
     * @return void
     */
    public function rollBack(): void
    {
        foreach ($this->connections as $connection) {
            $connection->rollBack();
        }
    }

    /**
     * Get loggers.
     *
     * @return Generator<Logger>
     */
    public function getLoggers(): Generator
    {
        foreach ($this->connections as $connection) {
            if (null === $connection->getLogger()) {
                continue;
            }

            yield $connection->getLogger();
        }
    }
}
