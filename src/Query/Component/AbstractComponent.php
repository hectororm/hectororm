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
use Hector\Query\StatementInterface;

abstract class AbstractComponent implements StatementInterface
{
    use IndentHelperTrait;

    public mixed $builder;

    /**
     * AbstractComponent constructor.
     *
     * @param mixed $builder
     */
    public function __construct(mixed $builder = null)
    {
        $this->builder = $builder;
    }

    /**
     * Get sub statement.
     *
     * @param Closure|StatementInterface|string|null $statement
     * @param array $binding
     * @param bool $encapsulate
     *
     * @return string|null
     */
    protected function getSubStatement(
        Closure|StatementInterface|string|null $statement,
        array &$binding,
        bool $encapsulate = true
    ): ?string {
        if (null === $statement) {
            return null;
        }

        // Statement
        if ($statement instanceof StatementInterface) {
            return $statement->getStatement($binding, $encapsulate);
        }

        // Callable statement
        if ($statement instanceof Closure) {
            $result = $statement($this->builder ?? $this);

            if (null !== $result) {
                return (string)$result;
            }

            return null;
        }

        return (string)$statement;
    }

    /**
     * Get sub statement.
     *
     * @param mixed $value
     * @param array $binding
     * @param bool $encapsulate
     *
     * @return string|null
     */
    protected function getSubStatementValue(mixed $value, array &$binding, bool $encapsulate = true): ?string
    {
        // Statement value
        if ($value instanceof StatementInterface) {
            return $value->getStatement($binding, $encapsulate);
        }

        // Callable statement value
        if ($value instanceof Closure) {
            return $this->getSubStatementValue($value->call($this), $binding, $encapsulate);
        }

        // Array statement value
        if (is_iterable($value)) {
            $statementValues = [];
            foreach ($value as $subValue) {
                // Binding
                array_push($binding, ...array_values((array)$subValue));

                if (is_array($subValue)) {
                    $statementValues[] = '(' . implode(', ', array_fill(0, count($subValue), '?')) . ')';
                    continue;
                }

                $statementValues[] = '?';
            }

            return '(' . implode(', ', $statementValues) . ')';
        }

        array_push($binding, $value);

        return '?';
    }
}