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

namespace Hector\Connection\Log;

use Countable;

/**
 * Class Logger.
 *
 * @package Hector\Connection
 */
class Logger implements Countable
{
    private array $logs = [];

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->logs = [];
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->logs);
    }

    /**
     * New log entry.
     *
     * @param string $statement
     * @param array $parameters
     * @param string|null $trace
     *
     * @return LogEntry
     */
    public function newEntry(string $statement, array $parameters = [], string $trace = null): LogEntry
    {
        return $this->logs[] = new LogEntry($statement, $parameters, $trace);
    }

    /**
     * Add log.
     *
     * @param LogEntry $logEntry
     */
    public function add(LogEntry $logEntry): void
    {
        $this->logs[] = $logEntry;
    }

    /**
     * Get logs.
     *
     * @return LogEntry[]
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}