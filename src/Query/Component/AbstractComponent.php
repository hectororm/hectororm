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
use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

abstract class AbstractComponent implements StatementInterface
{
    use EncapsulateHelperTrait;

    /**
     * AbstractComponent constructor.
     *
     * @param mixed $builder
     */
    public function __construct(public mixed $builder = null)
    {
    }

    /**
     * Get sub statement.
     *
     * @param Closure|StatementInterface|string|null $statement
     * @param BindParamList $bindParams
     * @param bool $encapsulate
     *
     * @return string|null
     */
    protected function getSubStatement(
        Closure|StatementInterface|string|null $statement,
        BindParamList $bindParams,
        bool $encapsulate = true
    ): ?string {
        if (null === $statement) {
            return null;
        }

        // Statement
        if ($statement instanceof StatementInterface) {
            return $statement->getStatement($bindParams, $encapsulate);
        }

        // Callable statement
        if ($statement instanceof Closure) {
            $result = $statement->call(
                $this->builder ?? $this,
                ...($args = $this->getClosureArgs()),
            );

            $str = '';

            foreach ($args as $arg) {
                if ($arg instanceof StatementInterface) {
                    $str .= $arg->getStatement($bindParams, $encapsulate);
                }
            }

            if (null !== $result) {
                $str .= $result;
            }

            return $str ?: null;
        }

        return $statement;
    }

    /**
     * Get sub statement.
     *
     * @param mixed $value
     * @param BindParamList $bindParams
     * @param bool $encapsulate
     *
     * @return string|null
     */
    protected function getSubStatementValue(mixed $value, BindParamList $bindParams, bool $encapsulate = true): ?string
    {
        // Statement value
        if ($value instanceof StatementInterface) {
            return $value->getStatement($bindParams, $encapsulate);
        }

        // Callable statement value
        if ($value instanceof Closure) {
            return $this->getSubStatementValue($value->call($this), $bindParams, $encapsulate);
        }

        // Array statement value
        if (is_iterable($value)) {
            $statementValues = [];
            foreach ($value as &$subValue) {
                if (is_array($subValue)) {
                    $paramsName = array_map(fn($value) => $bindParams->add($value)->getName(), $subValue);
                    $statementValues[] = '(' . implode(', ', array_map(fn($name) => ':' . $name, $paramsName)) . ')';
                    continue;
                }

                $statementValues[] = ':' . $bindParams->add($subValue)->getName();
            }

            return '( ' . implode(', ', $statementValues) . ' )';
        }

        return ':' . $bindParams->add($value)->getName();
    }

    /**
     * Get closure arguments.
     *
     * @return array
     */
    protected function getClosureArgs(): array
    {
        return [];
    }
}
