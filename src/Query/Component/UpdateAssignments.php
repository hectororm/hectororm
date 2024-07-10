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
use Hector\Query\StatementInterface;

class UpdateAssignments extends Assignments
{
    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if ($this->assignments instanceof StatementInterface) {
            return $this->assignments->getStatement($bindParams, $encapsulate);
        }

        return $this->encapsulate(
            implode(
                ', ',
                array_map(
                    function ($assignment) use (&$bindParams) {
                        if (!array_key_exists('value', $assignment)) {
                            return $this->getSubStatement($assignment['column'], $bindParams);
                        }

                        return sprintf(
                            '%s = %s',
                            $this->getSubStatement($assignment['column'], $bindParams),
                            $this->getSubStatementValue($assignment['value'], $bindParams)
                        );
                    },
                    $this->assignments
                )
            ),
            $encapsulate
        );
    }
}