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
use InvalidArgumentException;
use IteratorAggregate;
use Throwable;

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
     * @param bool|null $allTransactions See Connection::rollBack()
     *
     * @return void
     */
    public function rollBack(?bool $allTransactions = null): void
    {
        foreach ($this->connections as $connection) {
            $connection->rollBack($allTransactions);
        }
    }

    /**
     * Execute a unit of work inside a transaction on every connection of the set.
     *
     * Each connection follows Connection::transaction() semantics (savepoint when a
     * transaction is already active). Commits are not atomic across connections:
     * if one commit fails, connections already committed are not rolled back.
     *
     * With several attempts, the transaction is retried on every connection when one
     * of them reports a transient concurrency failure, unless a transaction was
     * already active on any connection.
     *
     * @template T
     *
     * @param callable(ConnectionSet): T $work
     * @param int $attempts Maximum number of attempts, at least 1
     *
     * @return T
     * @throws InvalidArgumentException If attempts is lower than 1
     * @throws Throwable
     * @see Connection::transaction()
     */
    public function transaction(callable $work, int $attempts = 1): mixed
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Transaction attempts must be greater than or equal to 1');
        }

        $operation = fn(): mixed => $work($this);
        foreach (array_reverse($this->connections) as $connection) {
            $operation = fn(): mixed => $connection->transaction($operation);
        }

        if (true === $this->inTransaction()) {
            return $operation();
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (Throwable $exception) {
                if ($attempt >= $attempts || false === $this->isRetryable($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Is a transaction active on any connection?
     *
     * @return bool
     */
    private function inTransaction(): bool
    {
        foreach ($this->connections as $connection) {
            if (true === $connection->inTransaction()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does any connection consider the failure retryable?
     *
     * @param Throwable $exception
     *
     * @return bool
     */
    private function isRetryable(Throwable $exception): bool
    {
        foreach ($this->connections as $connection) {
            if (true === $connection->getDriverInfo()->isRetryable($exception)) {
                return true;
            }
        }

        return false;
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
