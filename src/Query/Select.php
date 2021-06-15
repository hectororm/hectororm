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

use Hector\Query\Clause;
use Hector\Query\Component;

class Select implements StatementInterface
{
    public const INNER_JOIN = Component\Join::INNER_JOIN;
    public const LEFT_JOIN = Component\Join::LEFT_JOIN;
    public const RIGHT_JOIN = Component\Join::RIGHT_JOIN;
    public const ORDER_ASC = Component\Order::ORDER_ASC;
    public const ORDER_DESC = Component\Order::ORDER_DESC;

    use Clause\Columns;
    use Clause\From;
    use Clause\Join;
    use Clause\Where;
    use Clause\Group;
    use Clause\Having;
    use Clause\Order;
    use Clause\Limit;
    use Component\EncapsulateHelperTrait;

    protected bool $distinct = false;

    public function __construct()
    {
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
            ->resetColumns()
            ->resetFrom()
            ->resetJoin()
            ->resetWhere()
            ->resetGroup()
            ->resetHaving()
            ->resetOrder()
            ->resetLimit();

        return $this;
    }

    /**
     * Distinct result.
     *
     * @param bool $distinct
     *
     * @return static
     */
    public function distinct(bool $distinct = true): static
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        $str = 'SELECT';

        if ($this->distinct) {
            $str .= ' DISTINCT';
        }

        $fromStr = $this->from->getStatement($binding);
        $columnStr = $this->columns->getStatement($binding);

        if (null == $fromStr && $columnStr === null) {
            return null;
        }

        $str .= ' ' . ($columnStr ?? '*');

        if (null !== $fromStr) {
            $str .= ' FROM ' . $fromStr . rtrim(' ' . ($this->join->getStatement($binding) ?? ''));
        }

        $whereStr = $this->where->getStatement($binding);
        if (null !== $whereStr) {
            $str .= ' WHERE ' . $whereStr;
        }

        $str .= rtrim(' ' . ($this->group->getStatement($binding) ?? ''));

        $havingStr = $this->having->getStatement($binding);
        if (null !== $havingStr) {
            $str .= ' HAVING ' . $havingStr;
        }
        $str .= rtrim(' ' . ($this->order->getStatement($binding) ?? ''));
        $str .= rtrim(' ' . ($this->limit->getStatement($binding) ?? ''));

        return $this->encapsulate($str, $encapsulate);
    }
}