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

interface DriverCapabilities
{
    /**
     * Support of lock?
     *
     * @return bool
     */
    public function hasLock(): bool;

    /**
     * Support of lock and skip locked?
     *
     * @return bool
     */
    public function hasLockAndSkip(): bool;

    /**
     * Support of window functions?
     *
     * @return bool
     */
    public function hasWindowFunctions(): bool;

    /**
     * Support of JSON?
     *
     * @return bool
     */

    public function hasJson(): bool;

    /**
     * Has strict mode?
     *
     * @return bool
     */
    public function hasStrictMode(): bool;
}
