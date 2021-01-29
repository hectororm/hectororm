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

class Delete implements StatementInterface
{
    public const ORDER_ASC = Component\Order::ORDER_ASC;
    public const ORDER_DESC = Component\Order::ORDER_DESC;

    use Clause\From;
    use Clause\Where;
    use Clause\Order;
    use Clause\Limit;
    use Component\IndentHelperTrait;

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
            ->resetFrom()
            ->resetWhere()
            ->resetOrder()
            ->resetLimit();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        $fromStr = $this->from->getStatement($binding);

        if (null === $fromStr) {
            return null;
        }

        $str =
            'DELETE FROM' . PHP_EOL .
            ($this->from->getStatement($binding) ?? '');

        $whereStr = $this->where->getStatement($binding);
        if (null !== $whereStr) {
            $str .=
                'WHERE' . PHP_EOL .
                $whereStr;
        }

        $str .= $this->order->getStatement($binding) ?? '';
        $str .= $this->limit->getStatement($binding) ?? '';

        if ($encapsulate) {
            return '(' . PHP_EOL . $this->indent($str) . ')';
        }

        return $str;
    }
}