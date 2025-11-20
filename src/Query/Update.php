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

use Hector\Query\Component\Order;
use Hector\Query\Clause\BindParams;
use Hector\Query\Clause\From;
use Hector\Query\Clause\Assignments;
use Hector\Query\Clause\Where;
use Hector\Query\Clause\Limit;
use Hector\Query\Component\EncapsulateHelperTrait;
use Hector\Connection\Bind\BindParamList;

class Update implements StatementInterface
{
    public const ORDER_ASC = Order::ORDER_ASC;
    public const ORDER_DESC = Order::ORDER_DESC;

    use BindParams;
    use From;
    use Assignments;
    use Where;
    use Clause\Order;
    use Limit;
    use EncapsulateHelperTrait;

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
            ->resetFrom()
            ->resetAssignments()
            ->resetWhere()
            ->resetOrder()
            ->resetLimit();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        $this->mergeBindParamsTo($bindParams);

        $fromStr = $this->from->getStatement($bindParams);
        $assignmentsStr = $this->assignments->getStatement($bindParams);

        if (null === $fromStr || null === $assignmentsStr) {
            return null;
        }

        $str = 'UPDATE ' . ($this->from->getStatement($bindParams) ?? '') . ' SET ' . $assignmentsStr;

        $whereStr = $this->where->getStatement($bindParams);
        if (null !== $whereStr) {
            $str .= ' WHERE ' . $whereStr;
        }

        $str .= rtrim(' ' . ($this->order->getStatement($bindParams) ?? ''));
        $str .= rtrim(' ' . ($this->limit->getStatement($bindParams) ?? ''));

        return $this->encapsulate($str, $encapsulate);
    }
}
