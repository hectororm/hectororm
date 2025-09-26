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

class Union implements StatementInterface
{
    use Clause\Order;
    use Clause\Limit;
    use Component\EncapsulateHelperTrait;

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
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if (empty($this->selects)) {
            return null;
        }

        $selectStatements = [];
        /** @var Select $select */
        foreach ($this->selects as $select) {
            $selectStatements[] = $select->getStatement($bindParams, true);
        }

        $str = implode(' UNION ' . ($this->all ? 'ALL ' : 'DISTINCT '), $selectStatements);

        if (null !== $this->limit->getLimit() || count($this->order) > 0) {
            $encapsulate = true;
        }

        if ($encapsulate) {
            $str = $this->encapsulate($str, $encapsulate);
            $str .= rtrim(' ' . ($this->order->getStatement($bindParams) ?? ''));
            $str .= rtrim(' ' . ($this->limit->getStatement($bindParams) ?? ''));
        }

        return $str;
    }
}
