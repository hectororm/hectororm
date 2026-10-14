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

class MySQLCapabilities implements DriverCapabilities
{
    public function __construct(private DriverInfo $driverInfo)
    {
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
        return version_compare($this->driverInfo->getVersion(), '8.0.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasWindowFunctions(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '8.0.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasJson(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '5.7.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasStrictMode(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '5.7.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasRenameColumn(): bool
    {
        return version_compare($this->driverInfo->getVersion(), '8.0.0', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasTransactionalDdl(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasCheckConstraints(): bool
    {
        if ('mysql' !== $this->driverInfo->getDriver() ||
            1 !== preg_match('/^(\d+\.\d+\.\d+)(?:[-+].*)?$/', $this->driverInfo->getVersion(), $matches)) {
            return false;
        }

        // Server suffixes such as "-log" are not prerelease version qualifiers.
        return version_compare($matches[1], '8.0.16', '>=');
    }

    /**
     * @inheritDoc
     */
    public function hasDropCheck(): bool
    {
        return $this->hasCheckConstraints();
    }
}
