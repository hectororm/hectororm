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

use Closure;
use Countable;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\Select;
use Hector\Query\CompoundStatementInterface;
use Hector\Query\StatementInterface;

class Conditions extends AbstractComponent implements CompoundStatementInterface, Countable
{
    public const LINK_AND = 'AND';
    public const LINK_OR = 'OR';

    private array $conditions = [];

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->conditions);
    }

    /**
     * Merge columns.
     *
     * @param Conditions $conditions
     */
    public function merge(Conditions $conditions): void
    {
        array_push($this->conditions, ...$conditions->conditions);
    }

    /**
     * Add equal.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     * @param string $link
     */
    public function equal(
        Closure|StatementInterface|string $column,
        mixed $value,
        string $link = Conditions::LINK_AND
    ): void {
        $operator = '=';

        if (is_array($value) || $value instanceof Select) {
            $operator = 'IN';
        }

        if (null === $value) {
            $operator = 'IS NULL';
        }

        $this->add($column, $operator, $value, $link);
    }

    /**
     * Add equals.
     *
     * @param array $conditions
     */
    public function equals(array $conditions): void
    {
        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                if ($value instanceof Conditions) {
                    $this->merge($value);
                    continue;
                }

                if (is_array($value)) {
                    $this->equal(
                        array_shift($value),
                        array_shift($value),
                        array_shift($value) ?? Conditions::LINK_AND,
                    );
                    continue;
                }

                $this->add($value);
                continue;
            }

            $this->equal($key, $value);
        }
    }

    /**
     * Add column.
     *
     * @param Closure|StatementInterface|string $column
     * @param string|null $operator
     * @param mixed $value
     * @param string $link
     */
    public function add(
        Closure|StatementInterface|string $column,
        ?string $operator = null,
        mixed $value = null,
        string $link = Conditions::LINK_AND
    ): void {
        $this->conditions[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'link' => $link,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getClosureArgs(): array
    {
        return [
            new \Hector\Query\Statement\Conditions($this),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $statement = '';

        foreach ($this->conditions as &$condition) {
            // An empty IN / NOT IN list would render an invalid "IN (  )"; emit a
            // constant condition instead: IN [] is always false, NOT IN [] always true.
            if (null !== ($emptyInStatement = $this->getEmptyInStatement($condition))) {
                if (!empty($statement)) {
                    $statement .= ' ' . $condition['link'] . ' ';
                }

                $statement .= $emptyInStatement;
                continue;
            }

            if (null === ($subStatement = $this->getSubStatement($condition['column'], $bindParams, $driverInfo))) {
                continue;
            }

            if (!empty($statement)) {
                $statement .= ' ' . $condition['link'] . ' ';
            }

            $statement .= $subStatement;

            if (null === $condition['operator']) {
                continue;
            }

            $statement .= ' ' . $condition['operator'];

            if (null !== $condition['value']) {
                $statement .= ' ' . $this->getSubStatementValue($condition['value'], $bindParams, $driverInfo);
            }
        }

        return $statement ?: null;
    }

    /**
     * Get the constant statement for an empty IN / NOT IN list, or null otherwise.
     *
     * An empty IN list matches nothing (always false: "1 = 0"); an empty NOT IN list
     * matches everything (always true: "1 = 1"). This avoids emitting an invalid
     * "IN (  )" clause. The operator comparison is case-insensitive since callers may
     * pass it in any case through the generic where()/having().
     *
     * @param array $condition
     *
     * @return string|null
     */
    private function getEmptyInStatement(array $condition): ?string
    {
        $operator = is_string($condition['operator']) ? strtoupper(trim($condition['operator'])) : null;

        if ('IN' !== $operator && 'NOT IN' !== $operator) {
            return null;
        }

        $value = $condition['value'];

        if (!is_iterable($value)) {
            return null;
        }

        if (is_countable($value) ? 0 !== count($value) : [] !== iterator_to_array($value)) {
            return null;
        }

        return 'NOT IN' === $operator ? '1 = 1' : '1 = 0';
    }
}
