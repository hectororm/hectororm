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

class Delete implements StatementInterface
{
    public const ORDER_ASC = Component\Order::ORDER_ASC;
    public const ORDER_DESC = Component\Order::ORDER_DESC;

    use Clause\BindParams;
    use Clause\From;
    use Clause\Where;
    use Clause\Order;
    use Clause\Limit;
    use Component\EncapsulateHelperTrait;

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
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        $this->mergeBindParamsTo($bindParams);

        $fromStr = $this->from->getStatement($bindParams);

        if (null === $fromStr) {
            return null;
        }

        $str = 'DELETE FROM ' . ($this->from->getStatement($bindParams) ?? '');

        if (null !== ($whereStr = $this->where->getStatement($bindParams))) {
            $str .= ' WHERE ' . $whereStr;
        }

        $str .= rtrim(' ' . ($this->order->getStatement($bindParams) ?? ''));
        $str .= rtrim(' ' . ($this->limit->getStatement($bindParams) ?? ''));

        return $this->encapsulate($str, $encapsulate);
    }
}
