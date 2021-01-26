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
use Hector\Connection\Exception\NotFoundException;
use IteratorAggregate;

/**
 * Class ConnectionSet.
 *
 * @package Hector\Connection
 */
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
     */
    public function addConnection(Connection $connection): void
    {
        $this->connections[$connection->getName()] = $connection;
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
}