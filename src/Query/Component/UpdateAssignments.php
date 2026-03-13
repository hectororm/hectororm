<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query\Component;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\StatementInterface;

class UpdateAssignments extends Assignments
{
    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        if ($this->assignments instanceof StatementInterface) {
            return $this->assignments->getStatement($bindParams, $driverInfo);
        }

        $str = implode(
            ', ',
            array_map(
                function ($assignment) use (&$bindParams, $driverInfo) {
                    if (!array_key_exists('value', $assignment)) {
                        return $this->getSubStatement($assignment['column'], $bindParams, $driverInfo);
                    }

                    return sprintf(
                        '%s = %s',
                        $this->getSubStatement($assignment['column'], $bindParams, $driverInfo),
                        $this->getSubStatementValue($assignment['value'], $bindParams, $driverInfo)
                    );
                },
                $this->assignments
            )
        );

        return $str ?: null;
    }
}
