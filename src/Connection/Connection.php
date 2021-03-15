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

use Exception;
use Generator;
use Hector\Connection\Log\Logger;
use PDO;
use PDOStatement;

/**
 * Class Connection.
 *
 * @package Hector\Connection
 */
class Connection
{
    public const DEFAULT_NAME = 'default';

    private ?PDO $pdo = null;
    private ?PDO $readPdo = null;
    private int $transactions = 0;

    /**
     * Connection constructor.
     *
     * @param string $dsn
     * @param string|null $readDsn
     * @param string $name
     * @param Logger|null $logger
     */
    public function __construct(
        protected string $dsn,
        protected ?string $readDsn = null,
        protected string $name = self::DEFAULT_NAME,
        protected ?Logger $logger = null
    ) {
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'dsn' => $this->dsn,
            'readDsn' => $this->readDsn,
            'name' => $this->name,
            'logger' => $this->logger,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->dsn = $data['dsn'];
        $this->readDsn = $data['readDsn'];
        $this->name = $data['name'];
        $this->logger = $data['logger'];
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get logger.
     *
     * @return Logger|null
     */
    public function getLogger(): ?Logger
    {
        return $this->logger;
    }

    /**
     * Get PDO.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        if (null !== $this->pdo) {
            return $this->pdo;
        }

        $logEntry = $this->logger?->newEntry($this->name, 'CONNECTION ' . $this->dsn);

        $this->pdo = new PDO($this->dsn);

        $logEntry?->end();

        return $this->pdo;
    }

    /**
     * Get read only PDO.
     *
     * @return PDO
     */
    public function getReadPdo(): PDO
    {
        // Return read/write PDO if a transaction started
        if ($this->transactions > 0) {
            return $this->getPdo();
        }

        if (null !== $this->readPdo) {
            return $this->readPdo;
        }

        if (null === $this->readDsn) {
            return $this->getPdo();
        }

        $logEntry = $this->logger?->newEntry($this->name, 'CONNECTION ' . $this->readDsn);

        $this->readPdo = new PDO($this->readDsn);

        $logEntry?->end();

        return $this->readPdo;
    }

    /**
     * Get last insert id.
     *
     * @param string|null $name
     *
     * @return string
     */
    public function getLastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction(): void
    {
        $this->transactions++;

        if ($this->transactions === 1) {
            $this->getPdo()->beginTransaction();
        }
    }

    /**
     * Commit transaction.
     */
    public function commit(): void
    {
        if ($this->transactions <= 0) {
            return;
        }

        if ($this->transactions === 1) {
            $this->getPdo()->commit();
        }

        $this->transactions--;
    }

    /**
     * Roll back transaction.
     */
    public function rollBack(): void
    {
        if ($this->transactions > 0) {
            $this->getPdo()->rollBack();
            $this->transactions = 0;
        }
    }

    /**
     * In transaction?
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * PDO execute.
     *
     * @param PDO $pdo
     * @param string $statement
     * @param array $input_parameters
     *
     * @return PDOStatement
     */
    protected function pdoExecute(PDO $pdo, string $statement, array $input_parameters = []): PDOStatement
    {
        $logEntry = $this->logger?->newEntry(
            $this->name,
            $statement,
            $input_parameters,
            (new Exception())->getTraceAsString()
        );

        try {
            $stm = $pdo->prepare($statement);

            $iParam = 0;
            foreach ($input_parameters as $name => $parameter) {
                is_int($name) && $name = ++$iParam;

                if ($parameter instanceof BindParam) {
                    $stm->bindValue(
                        $name,
                        $parameter->getVariable(),
                        $parameter->getDataType()
                    );
                    continue;
                }

                $stm->bindValue(
                    $name,
                    $parameter,
                    BindParam::findDataType($parameter)
                );
            }

            $stm->execute();
        } finally {
            $logEntry?->end();
        }

        return $stm;
    }

    /**
     * Execute.
     *
     * @param string $statement
     * @param array $input_parameters
     *
     * @return int
     * @see \PDOStatement::execute()
     */
    public function execute(string $statement, array $input_parameters = []): int
    {
        $stm = $this->pdoExecute($this->getPdo(), $statement, $input_parameters);

        return $stm->rowCount();
    }

    /**
     * Fetch all.
     *
     * @param string $statement
     * @param array $input_parameters
     *
     * @return Generator
     * @see \PDOStatement::fetchAll()
     */
    public function fetchAll(string $statement, array $input_parameters = []): Generator
    {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        while ($row = $stm->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Fetch one.
     *
     * @param string $statement
     * @param array $input_parameters
     *
     * @return array|null
     * @see \PDOStatement::fetch()
     */
    public function fetchOne(string $statement, array $input_parameters = []): ?array
    {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        return $stm->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch column.
     *
     * @param string $statement
     * @param array $input_parameters
     * @param int $column
     *
     * @return Generator
     * @see \PDOStatement::fetchColumn()
     */
    public function fetchColumn(string $statement, array $input_parameters = [], int $column = 0): Generator
    {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        while (false !== ($row = $stm->fetchColumn($column))) {
            yield $row;
        }
    }
}