<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Connection\Driver;

use PDO;
use PDOException;
use Throwable;

class DriverInfo
{
    public function __construct(
        private string $driver,
        private string $version,
    ) {
    }

    public static function fromPDO(PDO $pdo): DriverInfo
    {
        $driver = strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $version = strtolower($pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

        $driver = match (true) {
            str_contains($version, 'mariadb') => 'mariadb',
            str_contains($version, 'vitess') => 'vitess',
            default => $driver,
        };

        $version = match ($driver) {
            'mariadb',
            'vitess' => preg_replace('/^(?:5\.5\.5-)?(\d+\.\d+\.\d+).*$/', '$1', $version),
            default => $version,
        };

        return new self($driver, $version);
    }

    /**
     * Get driver.
     *
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Get version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the character used to quote identifiers for this driver.
     *
     * @return string
     */
    public function getIdentifierQuote(): string
    {
        return match ($this->driver) {
            'pgsql', 'sqlite' => '"',
            default => '`',
        };
    }

    /**
     * Is the failure a transient concurrency error, worth retrying the whole transaction?
     *
     * Detects deadlocks, lock wait timeouts and serialization failures. The exception
     * chain is inspected, so wrapped PDO exceptions (e.g. by the ORM) are detected too.
     *
     * @param Throwable $exception
     *
     * @return bool
     */
    public function isRetryable(Throwable $exception): bool
    {
        for ($current = $exception; null !== $current; $current = $current->getPrevious()) {
            if (false === $current instanceof PDOException) {
                continue;
            }

            $sqlState = (string)($current->errorInfo[0] ?? $current->getCode());
            $driverCode = (int)($current->errorInfo[1] ?? 0);

            if (true === $this->isRetryableError($sqlState, $driverCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the SQLSTATE or driver error code a retryable concurrency error?
     *
     * @param string $sqlState
     * @param int $driverCode
     *
     * @return bool
     */
    private function isRetryableError(string $sqlState, int $driverCode): bool
    {
        return match ($this->driver) {
            // ER_LOCK_DEADLOCK, ER_LOCK_WAIT_TIMEOUT
            'mysql', 'mariadb', 'vitess' => '40001' === $sqlState || in_array($driverCode, [1213, 1205], true),
            // serialization_failure, deadlock_detected
            'pgsql' => in_array($sqlState, ['40001', '40P01'], true),
            // SQLITE_BUSY, SQLITE_LOCKED (extended codes keep the primary code in the low byte)
            'sqlite' => in_array($driverCode & 0xFF, [5, 6], true),
            default => '40001' === $sqlState,
        };
    }

    /**
     * Get capabilities.
     *
     * @return DriverCapabilities
     */
    public function getCapabilities(): DriverCapabilities
    {
        return match ($this->driver) {
            'mysql' => new MySQLCapabilities($this),
            'mariadb' => new MariaDBCapabilities($this),
            'sqlite' => new SQLiteCapabilities($this),
            'pgsql' => new PostgreSQLCapabilities($this),
            default => new UnknownCapabilities(),
        };
    }
}
