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

use Closure;
use Hector\Connection\Bind\BindParamList;

class Select implements StatementInterface
{
    public const INNER_JOIN = Component\Join::INNER_JOIN;
    public const LEFT_JOIN = Component\Join::LEFT_JOIN;
    public const RIGHT_JOIN = Component\Join::RIGHT_JOIN;
    public const ORDER_ASC = Component\Order::ORDER_ASC;
    public const ORDER_DESC = Component\Order::ORDER_DESC;

    use Clause\BindParams;
    use Clause\Columns;
    use Clause\From;
    use Clause\Join;
    use Clause\Where;
    use Clause\Group;
    use Clause\Having;
    use Clause\Order;
    use Clause\Limit;
    use Component\EncapsulateHelperTrait;

    protected bool|Closure $distinct = false;

    public function __construct(?BindParamList $binds = null)
    {
        $this->binds = $binds ?? new BindParamList();
        $this->reset();
    }

    /**
     * Reset.
     *
     * @return static
     */
    public function reset(): static
    {
        $this
            ->resetBindParams()
            ->resetColumns()
            ->resetFrom()
            ->resetJoin()
            ->resetWhere()
            ->resetGroup()
            ->resetHaving()
            ->resetOrder()
            ->resetLimit();
        is_bool($this->distinct) && $this->distinct = false;

        return $this;
    }

    /**
     * Distinct result.
     *
     * @param bool|Closure $distinct
     *
     * @return static
     */
    public function distinct(bool|Closure $distinct = true): static
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        $this->mergeBindParamsTo($bindParams);

        $columnStr = $this->columns->getStatement($bindParams);
        $whereStr = $this->where->getStatement($bindParams);
        $havingStr = $this->having->getStatement($bindParams);
        $groupStr = $this->group->getStatement($bindParams);
        $orderStr = $this->order->getStatement($bindParams);
        $limitStr = $this->limit->getStatement($bindParams);
        $joinStr = $this->join->getStatement($bindParams);
        $fromStr = $this->from->getStatement($bindParams);

        $str = 'SELECT';

        if ((true === $this->distinct || ($this->distinct instanceof Closure && true === ($this->distinct)()))) {
            $str .= ' DISTINCT';
        }

        if (null == $fromStr && $columnStr === null) {
            return null;
        }

        $str .= ' ' . ($columnStr ?? '*');

        if (null !== $fromStr) {
            $str .= ' FROM ' . $fromStr . rtrim(' ' . ($joinStr ?? ''));
        }

        if (null !== $whereStr) {
            $str .= ' WHERE ' . $whereStr;
        }

        $str .= rtrim(' ' . ($groupStr ?? ''));

        if (null !== $havingStr) {
            $str .= ' HAVING ' . $havingStr;
        }
        $str .= rtrim(' ' . ($orderStr ?? ''));
        $str .= rtrim(' ' . ($limitStr ?? ''));

        return $this->encapsulate($str, $encapsulate);
    }
}
