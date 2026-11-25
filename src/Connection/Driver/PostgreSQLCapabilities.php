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

class PostgreSQLCapabilities implements DriverCapabilities
{
    public function __construct(private DriverInfo $driverInfo)
    {
    }

    /**
     * @inheritDoc
     */
    public function hasGeneratedColumns(): bool
    {
        return $this->hasGeneratedColumnsSince('12.0');
    }

    /**
     * @inheritDoc
     */
    public function hasVirtualGeneratedColumns(): bool
    {
        return $this->hasGeneratedColumnsSince('18.0');
    }

    private function hasGeneratedColumnsSince(string $minimumVersion): bool
    {
        // PostgreSQL uses major.minor versions since 10; PDO may include a distribution suffix.
        if ('pgsql' !== $this->driverInfo->getDriver() ||
            1 !== preg_match('/^(\d+\.\d+(?:\.\d+)?)(?:[-+ ].*)?$/', $this->driverInfo->getVersion(), $matches)) {
            return false;
        }

        return version_compare($matches[1], $minimumVersion, '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasLock(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasLockAndSkip(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '9.5.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasWindowFunctions(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '9.1.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasJson(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '9.2.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasStrictMode(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasRenameColumn(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasTransactionalDdl(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function hasCheckConstraints(): bool
    {
        return 'pgsql' === $this->driverInfo->getDriver();
    }

    /**
     * @inheritDoc
     */
    public function hasDropCheck(): bool
    {
        return false;
    }
}
