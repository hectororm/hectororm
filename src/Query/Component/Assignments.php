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

namespace Hector\Query\Component;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

class Assignments extends AbstractComponent
{
    private array|StatementInterface $assignments = [];

    /**
     * Is statement assignment.
     *
     * @return bool
     */
    public function isStatement(): bool
    {
        return !is_array($this->assignments);
    }

    /**
     * Assignment.
     *
     * @param StatementInterface|string $column
     * @param mixed $value
     * @param int|null $type PDO::PARAM_*
     */
    public function assignment(StatementInterface|string $column, mixed $value, ?int $type = null): void
    {
        if (!is_array($this->assignments)) {
            $this->assignments = [];
        }

        $this->assignments[] = [
            'column' => $column,
            'value' => $value,
            'type' => $type,
        ];
    }

    /**
     * Assignments.
     *
     * @param array|StatementInterface $values
     */
    public function assignments(array|StatementInterface $values): void
    {
        if ($values instanceof StatementInterface) {
            $this->assignments = $values;
            return;
        }

        foreach ($values as $column => $value) {
            if (is_int($column)) {
                $this->assignments[] = ['column' => $value];
                continue;
            }

            $this->assignment($column, $value);
        }
    }

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