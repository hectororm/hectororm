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

trait Columns
{
    public Component\Columns $columns;

    /**
     * Reset columns.
     *
     * @return static
     */
    public function resetColumns(): static
    {
        $this->columns = new Component\Columns($this);

        return $this;
    }

    /**
     * Column.
     *
     * @param Closure|StatementInterface|string $column
     * @param string|null $alias
     *
     * @return static
     */
    public function column(Closure|StatementInterface|string $column, ?string $alias = null): static
    {
        $this->columns->column($column, $alias);

        return $this;
    }

    /**
     * Columns.
     *
     * @param Closure|StatementInterface|string ...$column
     *
     * @return static
     */
    public function columns(Closure|StatementInterface|string ...$column): static
    {
        $this->columns->columns(...$column);

        return $this;
    }
}