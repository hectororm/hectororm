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

class UnknownCapabilities implements DriverCapabilities
{
    /**
     * @inheritDoc
     */
    public function hasLock(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasLockAndSkip(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasWindowFunctions(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasJson(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function hasStrictMode(): bool
    {
        return false;
    }
}
