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
use Hector\Connection\Driver\DriverCapabilities;

interface StatementInterface
{
    /**
     * Get statement.
     *
     * @param BindParamList $bindParams Bind parameters
     * @param DriverCapabilities|null $driverCapabilities Driver capabilities (default: null, fallback to backtick quoting)
     * @param bool $encapsulate Encapsulate statement? (default: false)
     *
     * @return string|null
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverCapabilities $driverCapabilities = null,
        bool $encapsulate = false,
    ): ?string;
}
