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
        $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        $driver = match (true) {
            str_contains($driver, 'mariadb') => 'mariadb',
            str_contains($driver, 'vitess') => 'vitess',
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
