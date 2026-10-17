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

/**
 * Value of an UPDATE assignment computed from the assigned column itself (e.g. "stock = stock - 1").
 *
 * Such values are only meaningful on the right side of an UPDATE assignment; they are rejected anywhere else.
 */
interface AssignmentValueInterface
{
    /**
     * Get assignment statement.
     *
     * @param string $column Rendered target column (left side of the assignment)
     * @param BindParamList $bindParams
     * @param DriverInfo|null $driverInfo
     *
     * @return string Right side of the assignment
     */
    public function getAssignmentStatement(
        string $column,
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): string;
}
