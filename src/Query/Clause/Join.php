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

namespace Hector\Query\Clause;

use Hector\Query\Component;
use Hector\Query\StatementInterface;

trait Join
{
    public Component\Join $join;

    /**
     * Reset join.
     *
     * @return static
     */
    public function resetJoin(): static
    {
        $this->join = new Component\Join($this);

        return $this;
    }

    /**
     * Inner join.
     *
     * @param StatementInterface|string $table
     * @param StatementInterface|string|iterable|null $condition
     * @param string|null $alias
     *
     * @return static
     */
    public function innerJoin(
        StatementInterface|string $table,
        StatementInterface|string|iterable|null $condition = null,
        ?string $alias = null
    ): static {
        $this->join->join(Component\Join::INNER_JOIN, $table, $condition, $alias);

        return $this;
    }

    /**
     * Left join.
     *
     * @param StatementInterface|string $table
     * @param StatementInterface|string|iterable|null $condition
     * @param string|null $alias
     *
     * @return static
     */
    public function leftJoin(
        StatementInterface|string $table,
        StatementInterface|string|iterable|null $condition = null,
        ?string $alias = null
    ): static {
        $this->join->join(Component\Join::LEFT_JOIN, $table, $condition, $alias);

        return $this;
    }

    /**
     * Right join.
     *
     * @param StatementInterface|string $table
     * @param StatementInterface|string|iterable|null $condition
     * @param string|null $alias
     *
     * @return static
     */
    public function rightJoin(
        StatementInterface|string $table,
        StatementInterface|string|iterable|null $condition = null,
        ?string $alias = null
    ): static {
        $this->join->join(Component\Join::RIGHT_JOIN, $table, $condition, $alias);

        return $this;
    }
}