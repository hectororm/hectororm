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

use Hector\Connection\Bind\BindParamList;

class LogEntry
{
    public const TYPE_CONNECTION = 'connection';
    public const TYPE_QUERY = 'query';

    private float $start;
    private ?float $end = null;

    /**
     * LogEntry constructor.
     *
     * @param string $connection
     * @param string|null $statement
     * @param BindParamList|array $parameters
     * @param string|null $trace
     * @param string $type
     */
    public function __construct(
        private string $connection,
        private ?string $statement,
        private BindParamList|array $parameters = [],
        private ?string $trace = null,
        private string $type = self::TYPE_QUERY,
    ) {
        $this->start = microtime(true);
    }

    /**
     * End timer.
     */
    public function end(): void
    {
        $this->end = microtime(true);
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * With statement.
     *
     * @param string $statement
     *
     * @return LogEntry
     */
    public function withStatement(string $statement): LogEntry
    {
        $me = clone $this;
        $me->statement = $statement;

        return $me;
    }

    /**
     * Get statement parameters.
     *
     * @return iterable
     */
    public function getParameters(): iterable
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
