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
use InvalidArgumentException;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Exception\ConnectionException;
use Hector\Connection\Log\LogEntry;
use Hector\Connection\Log\Logger;
use PDO;
use PDOException;
use PDOStatement;
use SensitiveParameter;
use Throwable;

class Connection
{
    public const DEFAULT_NAME = 'default';

    private ?PDO $pdo = null;
    private ?PDO $readPdo = null;
    private ?DriverInfo $driverInfo = null;
    private int $transactions = 0;
    private bool $ownsTransaction = false;

    /**
     * Connection constructor.
     *
     * @param string $dsn
     * @param string|null $username
     * @param string|null $password
     * @param string|null $readDsn
     * @param string $name
     * @param Logger|null $logger
     * @param array $options Driver-specific PDO connection options (PDO constructor $options),
     *                       shared by both the read/write and read-only connections.
     *                       Beware that options like PDO::ATTR_AUTOCOMMIT or PDO::ATTR_ERRMODE
     *                       may interfere with the internal transaction handling.
     */
    public function __construct(
        protected string $dsn,
        #[SensitiveParameter]
        private ?string $username = null,
        #[SensitiveParameter]
        private ?string $password = null,
        protected ?string $readDsn = null,
        protected string $name = self::DEFAULT_NAME,
        protected ?Logger $logger = null,
        protected array $options = []
    ) {
    }

    /**
     * Mask credentials embedded in a DSN before exposing it (e.g. in logs).
     *
     * A PDO DSN may carry `user=`, `password=` or `pwd=` parameters; their
     * values are replaced with `***` so they never reach a logger.
     *
     * @param string $dsn
     *
     * @return string
     */
    private function sanitizeDsn(string $dsn): string
    {
        return preg_replace(
            '/\b(password|pwd|user)=[^;]*/i',
            '$1=***',
            $dsn
        );
    }

    /**
     * Create connection from PDO objects.
     *
     * @param PDO $pdo
     * @param PDO|null $readPdo
     * @param string $name
     * @param Logger|null $logger
     *
     * @return self
     */
    public static function fromPdo(
        PDO $pdo,
        ?PDO $readPdo = null,
        string $name = self::DEFAULT_NAME,
        ?Logger $logger = null,
    ): self {
        $connection = new self('PDO', name: $name, logger: $logger);
        $connection->pdo = $pdo;
        $connection->readPdo = $readPdo;

        return $connection;
    }

    /**
     * PHP serialize method.
     *
     * @return array
     * @throws ConnectionException
     */
    public function __serialize(): array
    {
        $this->dsn === 'PDO' && throw new ConnectionException('Connection created from PDO');

        return [
            'dsn' => $this->dsn,
            'username' => $this->username,
            'password' => $this->password,
            'readDsn' => $this->readDsn,
            'name' => $this->name,
            'logger' => $this->logger,
            'options' => $this->options,
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
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->readDsn = $data['readDsn'];
        $this->name = $data['name'];
        $this->logger = $data['logger'];
        $this->options = $data['options'] ?? [];
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

        $logEntry = $this->logger?->newEntry(
            $this->name,
            'CONNECTION ' . $this->sanitizeDsn($this->dsn),
            type: LogEntry::TYPE_CONNECTION
        );

        try {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $exception) {
            // Do not chain the PDOException: on PHP < 8.2 its stack trace holds
            // the password passed to the PDO constructor. Re-use its message
            // only (e.g. "SQLSTATE[HY000] [1045] Access denied..."), which is
            // safe and useful, then drop the dangerous trace.
            throw new ConnectionException(
                sprintf('Unable to connect to "%s": %s', $this->name, $exception->getMessage())
            );
        } finally {
            $logEntry?->end();
        }

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

        $logEntry = $this->logger?->newEntry($this->name, 'CONNECTION ' . $this->sanitizeDsn($this->readDsn));

        try {
            $this->readPdo = new PDO($this->readDsn, $this->username, $this->password, $this->options);
        } catch (PDOException $exception) {
            throw new ConnectionException(
                sprintf('Unable to connect to "%s": %s', $this->name, $exception->getMessage())
            );
        } finally {
            $logEntry?->end();
        }

        return $this->readPdo;
    }

    /**
     * Get driver name.
     *
     * @return string
     * @deprecated 1.0.0-beta8 No longer used by internal code and not recommended
     * @see Connection::getDriverInfo()
     */
    public function getDriverName(): string
    {
        return $this->getDriverInfo()->getDriver();
    }

    /**
     * Get driver info.
     *
     * @return DriverInfo
     */
    public function getDriverInfo(): DriverInfo
    {
        return $this->driverInfo ??= DriverInfo::fromPDO($this->getPdo());
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
        if (false === $this->getPdo()->inTransaction()) {
            // DDL may have committed behind our back. Start a real transaction
            // rather than letting a stale counter route subsequent writes to autocommit.
            $this->transactions = 0;
            $this->ownsTransaction = false;
            $this->getPdo()->beginTransaction();
            $this->ownsTransaction = true;
        } else {
            // At level zero the transaction belongs to the PDO caller, not Hector.
            if (0 === $this->transactions) {
                $this->ownsTransaction = false;
            }
            $this->execute('SAVEPOINT hector_tx_' . ($this->transactions + 1));
        }

        // A failed BEGIN or SAVEPOINT must not increment the counter (#69).
        $this->transactions++;
    }

    /**
     * Commit transaction.
     */
    public function commit(): void
    {
        if ($this->transactions <= 0) {
            return;
        }

        if (false === $this->getPdo()->inTransaction()) {
            $this->transactions = 0;
            $this->ownsTransaction = false;

            return;
        }

        if (1 === $this->transactions && true === $this->ownsTransaction) {
            $this->getPdo()->commit();
        } else {
            $this->execute('RELEASE SAVEPOINT hector_tx_' . $this->transactions);
        }

        $this->transactions--;
        if (0 === $this->transactions) {
            $this->ownsTransaction = false;
        }
    }

    /**
     * Roll back transaction.
     *
     * @param bool|null $allTransactions True rolls back all Hector-managed levels;
     *                                  false only the current level. Null means true
     *                                  in 1.x and will mean false in 2.0.
     */
    public function rollBack(?bool $allTransactions = null): void
    {
        if ($this->transactions <= 0) {
            return;
        }

        if (false === $this->getPdo()->inTransaction()) {
            $this->transactions = 0;
            $this->ownsTransaction = false;

            return;
        }

        if (null === $allTransactions && $this->transactions > 1) {
            trigger_error(
                'Calling rollBack() without $allTransactions in a nested transaction is deprecated: '
                . 'it rolls back all levels in 1.x and only the current level in 2.0. '
                . 'Pass allTransactions: true to keep the current behavior.',
                E_USER_DEPRECATED,
            );
        }

        $level = ($allTransactions ?? true) ? 1 : $this->transactions;
        if (1 === $level && true === $this->ownsTransaction) {
            $this->getPdo()->rollBack();
            $this->transactions = 0;
            $this->ownsTransaction = false;

            return;
        }

        // Even an all-level rollback must preserve an externally owned transaction.
        try {
            $this->execute('ROLLBACK TO SAVEPOINT hector_tx_' . $level);
            $this->execute('RELEASE SAVEPOINT hector_tx_' . $level);
        } finally {
            $this->transactions = $this->getPdo()->inTransaction() ? $level - 1 : 0;
            if (0 === $this->transactions) {
                $this->ownsTransaction = false;
            }
        }
    }

    /**
     * Execute a unit of work inside a transaction.
     *
     * The callback receives the connection. Its result is returned after commit;
     * on failure the work is rolled back and the original exception is rethrown.
     * When a transaction is already active, the work runs inside a savepoint so
     * that a failure only discards the nested work, not the outer transaction.
     *
     * With several attempts, the whole transaction is retried after a transient
     * concurrency failure (deadlock, lock wait timeout...), as detected by
     * DriverInfo::isRetryable(). Nested calls are never retried: such failures
     * usually abort the outer transaction, which is the one to retry.
     *
     * @template T
     *
     * @param callable(Connection): T $work
     * @param int $attempts Maximum number of attempts, at least 1
     *
     * @return T
     * @throws InvalidArgumentException If attempts is lower than 1
     * @throws Throwable
     * @see DriverInfo::isRetryable()
     */
    public function transaction(callable $work, int $attempts = 1): mixed
    {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Transaction attempts must be greater than or equal to 1');
        }

        if (true === $this->getPdo()->inTransaction()) {
            return $this->runTransaction($work);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->runTransaction($work);
            } catch (Throwable $exception) {
                if ($attempt >= $attempts || false === $this->getDriverInfo()->isRetryable($exception)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Execute a single transaction attempt.
     *
     * @template T
     *
     * @param callable(Connection): T $work
     *
     * @return T
     * @throws Throwable
     */
    private function runTransaction(callable $work): mixed
    {
        $this->beginTransaction();

        try {
            $result = $work($this);
            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            try {
                $this->rollBack(allTransactions: false);
            } catch (Throwable) {
                // Keep the original failure: the rollback error is secondary.
            }

            throw $exception;
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
     * @param BindParamList|array $input_parameters
     *
     * @return PDOStatement
     */
    protected function pdoExecute(PDO $pdo, string $statement, BindParamList|array $input_parameters = []): PDOStatement
    {
        is_array($input_parameters) && $input_parameters = new BindParamList($input_parameters);
        $logEntry = $this->logger?->newEntry(
            $this->name,
            $statement,
            $input_parameters,
            (new Exception())->getTraceAsString()
        );

        try {
            $stm = $pdo->prepare($statement);

            foreach ($input_parameters as $parameter) {
                $stm->bindValue(
                    $parameter->getName(),
                    $parameter->getValue(),
                    $parameter->getDataType()
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
     * @param BindParamList|array $input_parameters
     *
     * @return int
     * @see PDOStatement::execute
     */
    public function execute(string $statement, BindParamList|array $input_parameters = []): int
    {
        $stm = $this->pdoExecute($this->getPdo(), $statement, $input_parameters);

        return $stm->rowCount();
    }

    /**
     * Fetch all.
     *
     * @param string $statement
     * @param BindParamList|array $input_parameters
     *
     * @return array<array>
     * @see PDOStatement::fetchAll
     */
    public function fetchAll(string $statement, BindParamList|array $input_parameters = []): array
    {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        return $stm->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Yield all.
     *
     * @param string $statement
     * @param BindParamList|array $input_parameters
     *
     * @return Generator<array>
     */
    public function yieldAll(string $statement, BindParamList|array $input_parameters = []): Generator
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
     * @param BindParamList|array $input_parameters
     *
     * @return array|null
     * @see PDOStatement::fetch
     */
    public function fetchOne(string $statement, BindParamList|array $input_parameters = []): ?array
    {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        return $stm->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch column.
     *
     * @param string $statement
     * @param BindParamList|array $input_parameters
     * @param int $column
     *
     * @return array
     * @see PDOStatement::fetchColumn
     */
    public function fetchColumn(
        string $statement,
        BindParamList|array $input_parameters = [],
        int $column = 0
    ): array {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        return $stm->fetchAll(PDO::FETCH_COLUMN, $column);
    }

    /**
     * Yield column.
     *
     * @param string $statement
     * @param BindParamList|array $input_parameters
     * @param int $column
     *
     * @return Generator
     * @see PDOStatement::fetchColumn
     */
    public function yieldColumn(
        string $statement,
        BindParamList|array $input_parameters = [],
        int $column = 0
    ): Generator {
        $stm = $this->pdoExecute($this->getReadPdo(), $statement, $input_parameters);

        // Iterate the statement with FETCH_COLUMN instead of looping on fetchColumn():
        // the latter returns false both at end-of-cursor and when the column value
        // itself is false (e.g. a boolean false from PostgreSQL), which would truncate
        // the result set. Traversing the statement stops only on a real end-of-cursor.
        $stm->setFetchMode(PDO::FETCH_COLUMN, $column);

        yield from $stm;
    }
}
