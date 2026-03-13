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

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\Clause\BindParams;
use Hector\Query\Clause\From;
use Hector\Query\Clause\Limit;
use Hector\Query\Clause\Where;
use Hector\Query\Component\Order;

class Delete implements CompoundStatementInterface
{
    public const ORDER_ASC = Order::ORDER_ASC;
    public const ORDER_DESC = Order::ORDER_DESC;

    use BindParams;
    use From;
    use Where;
    use Clause\Order;
    use Limit;

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
            ->resetWhere()
            ->resetOrder()
            ->resetLimit();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $this->mergeBindParamsTo($bindParams);

        $fromStr = $this->from->getStatement($bindParams, $driverInfo);

        if (null === $fromStr) {
            return null;
        }

        $str = 'DELETE FROM ' . ($this->from->getStatement($bindParams, $driverInfo) ?? '');

        if (null !== ($whereStr = $this->where->getStatement($bindParams, $driverInfo))) {
            $str .= ' WHERE ' . $whereStr;
        }

        $str .= rtrim(' ' . ($this->order->getStatement($bindParams, $driverInfo) ?? ''));
        $str .= rtrim(' ' . ($this->limit->getStatement($bindParams, $driverInfo) ?? ''));

        return $str;
    }
}
