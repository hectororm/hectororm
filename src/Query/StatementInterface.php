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

namespace Hector\Query;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;

interface StatementInterface
{
    /**
     * Get statement.
     *
     * @param BindParamList $bindParams Bind parameters
     * @param DriverInfo|null $driverInfo Driver info (default: null, fallback to backtick quoting)
     *
     * @return string|null
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string;
}
