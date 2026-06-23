<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\StatementInterface;

class RandomFunction implements StatementInterface
{
    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        return $this->randomFunction($driverInfo);
    }

    /**
     * Get the SQL function returning a random value for the given driver.
     *
     * Defaults to the MySQL/MariaDB `RAND()` (also used when no driver is known);
     * SQLite and PostgreSQL use `RANDOM()`.
     *
     * @param DriverInfo|null $driverInfo
     *
     * @return string
     */
    protected function randomFunction(?DriverInfo $driverInfo): string
    {
        return match ($driverInfo?->getDriver()) {
            'pgsql', 'sqlite' => 'RANDOM()',
            default => 'RAND()',
        };
    }
}
