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

class Union implements StatementInterface
{
    use Clause\Order;
    use Clause\Limit;
    use Component\IndentHelperTrait;

    private bool $all = false;
    private array $selects = [];

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
            ->resetOrder()
            ->resetLimit();

        return $this;
    }

    /**
     * All.
     *
     * @param bool $all
     *
     * @return static
     */
    public function all(bool $all = true): static
    {
        $this->all = $all;

        return $this;
    }

    /**
     * Add select.
     *
     * @param Select ...$select
     *
     * @return static
     */
    public function addSelect(Select ...$select): static
    {
        array_push($this->selects, ...$select);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        if (empty($this->selects)) {
            return null;
        }

        $selectStatements = [];
        /** @var Select $select */
        foreach ($this->selects as $select) {
            $selectStatements[] = $select->getStatement($binding, true);
        }

        $str = implode(
            PHP_EOL . 'UNION ' . ($this->all ? 'ALL' : 'DISTINCT') . PHP_EOL,
            $selectStatements
        ) . PHP_EOL;

        if (null !== $this->limit->getLimit() || count($this->order) > 0) {
            $encapsulate = true;
        }

        if ($encapsulate) {
            $str =
                '(' . PHP_EOL .
                $this->indent($str) .
                ')' . PHP_EOL;

            $str .= $this->order->getStatement($binding) ?? '';
            $str .= $this->limit->getStatement($binding) ?? '';
        }

        return $str;
    }
}