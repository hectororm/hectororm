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

trait Order
{
    public Component\Order $order;

    /**
     * Reset order.
     *
     * @return static
     */
    public function resetOrder(): static
    {
        $this->order = new Component\Order($this);

        return $this;
    }

    /**
     * Order by.
     *
     * @param Closure|StatementInterface|string $column
     * @param string|null $order
     *
     * @return static
     */
    public function orderBy(Closure|StatementInterface|string $column, ?string $order = null): static
    {
        $this->order->orderBy($column, $order);

        return $this;
    }

    /**
     * Randomize results.
     *
     * @return static
     */
    public function random(): static
    {
        $this->orderBy('RAND()');

        return $this;
    }
}
