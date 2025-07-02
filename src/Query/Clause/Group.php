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

use Closure;
use Hector\Query\Component;
use Hector\Query\StatementInterface;

trait Group
{
    public Component\Group $group;

    /**
     * Reset group.
     *
     * @return static
     */
    public function resetGroup(): static
    {
        $this->group = new Component\Group($this);

        return $this;
    }

    /**
     * Group by.
     *
     * @param Closure|StatementInterface|string $column
     *
     * @return static
     */
    public function groupBy(Closure|StatementInterface|string $column): static
    {
        $this->group->groupBy($column);

        return $this;
    }

    /**
     * Group by with rollup.
     *
     * @param bool $withRollup
     *
     * @return static
     */
    public function groupByWithRollup(bool $withRollup = true): static
    {
        $this->group->withRollup($withRollup);

        return $this;
    }
}