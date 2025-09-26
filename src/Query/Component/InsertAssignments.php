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

namespace Hector\Query\Component;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Statement\Raw;
use Hector\Query\StatementInterface;

class InsertAssignments extends Assignments
{
    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if ($this->assignments instanceof StatementInterface) {
            return $this->assignments->getStatement($bindParams, $encapsulate);
        }

        $keys = [];
        $values = [];

        if (0 === count($this->assignments)) {
            return null;
        }

        foreach ($this->assignments as $assignment) {
            if (false === array_key_exists('value', $assignment)) {
                $tmp = explode('=', $assignment['column'], 2);
                $assignment['column'] = trim($tmp[0]);
                $assignment['value'] = new Raw(trim($tmp[1]));
            }

            $keys[] = $this->getSubStatement($assignment['column'], $bindParams);
            $values[] = $this->getSubStatementValue($assignment['value'] ?? null, $bindParams);
        }

        return $this->encapsulate(implode(', ', $keys)) . ' VALUES ' . $this->encapsulate(implode(', ', $values));
    }
}
