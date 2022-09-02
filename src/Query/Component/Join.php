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

class Join extends AbstractComponent
{
    public const INNER_JOIN = 'INNER';
    public const LEFT_JOIN = 'LEFT';
    public const RIGHT_JOIN = 'RIGHT';

    private array $joins = [];

    /**
     * Join.
     *
     * @param string $join
     * @param StatementInterface|string $table
     * @param StatementInterface|string|iterable|null $condition
     * @param string|null $alias
     */
    public function join(
        string $join,
        StatementInterface|string $table,
        StatementInterface|string|iterable|null $condition = null,
        string $alias = null
    ): void {
        $this->joins[] = [
            'join' => $join,
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition,
        ];
    }

    /**
     * Get alias of jointure table.
     *
     * @param StatementInterface|string $table
     *
     * @return string|false Return false if it does not exist, the alias name if it has one or true.
     */
    public function getAlias(StatementInterface|string $table): string|false
    {
        foreach ($this->joins as $join) {
            if ($join['table'] == $table && $join['alias']) {
                return $join['alias'];
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        return $this->encapsulate(
            implode(
                ' ',
                array_map(
                    function ($join) use (&$bindParams) {
                        $str = sprintf('%s JOIN %s', $join['join'], $this->getSubStatement($join['table'], $bindParams));

                        if (null !== $join['alias']) {
                            $str .= sprintf(' AS %s', $join['alias']);
                        }

                        $joinCondition = $this->getJoinCondition($join['condition'], $bindParams);
                        if (null !== $joinCondition) {
                            $str .= sprintf(' ON ( %s )', $joinCondition);
                        }

                        return $str;
                    },
                    $this->joins
                )
            ),
            $encapsulate
        );
    }

    /**
     * Get join condition.
     *
     * @param StatementInterface|string|iterable|null $condition
     * @param BindParamList $bindParams
     *
     * @return string|null
     */
    private function getJoinCondition(StatementInterface|string|iterable|null $condition, BindParamList $bindParams): ?string
    {
        if (null === $condition) {
            return null;
        }

        if (is_iterable($condition)) {
            $conditions = [];

            foreach ($condition as $key => $value) {
                if (is_numeric($key)) {
                    $conditions[] = $this->getSubStatement($value, $bindParams, false);
                    continue;
                }

                $conditions[] = sprintf(
                    '%s = %s',
                    $this->getSubStatement($key, $bindParams, false),
                    $this->getSubStatement($value, $bindParams, false)
                );
            }

            return implode(' AND ', $conditions);
        }

        return $this->getSubStatement($condition, $bindParams, false);
    }
}