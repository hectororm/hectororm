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

/**
 * Class LogEntry.
 *
 * @package Hector\Connection
 */
class LogEntry
{
    private float $start;
    private ?float $end = null;

    /**
     * LogEntry constructor.
     *
     * @param string $connection
     * @param string $statement
     * @param array $parameters
     * @param string|null $trace
     */
    public function __construct(
        private string $connection,
        private string $statement,
        private array $parameters = [],
        private ?string $trace = null
    ) {
        $this->start = microtime(true);
    }

    /**
     * End timer.
     */
    public function end()
    {
        $this->end = microtime(true);
    }

    /**
     * Get start.
     *
     * @return float
     */
    public function getStart(): float
    {
        return $this->start;
    }

    /**
     * Get end.
     *
     * @return float|null
     */
    public function getEnd(): ?float
    {
        return $this->end;
    }

    /**
     * Get duration.
     *
     * @return float|null
     */
    public function getDuration(): ?float
    {
        if (null === $this->end) {
            return null;
        }

        return $this->end - $this->start;
    }

    /**
     * Get connection.
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Get statement.
     *
     * @return string
     */
    public function getStatement(): string
    {
        return $this->statement;
    }

    /**
     * Get statement parameters.
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get trace.
     *
     * @return string|null
     */
    public function getTrace(): ?string
    {
        return $this->trace;
    }
}