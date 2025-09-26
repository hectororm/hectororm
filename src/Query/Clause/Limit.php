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

trait Limit
{
    public Component\Limit $limit;

    /**
     * Reset limit.
     *
     * @return static
     */
    public function resetLimit(): static
    {
        $this->limit = new Component\Limit($this);

        return $this;
    }

    /**
     * Limit.
     *
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return static
     */
    public function limit(?int $limit, ?int $offset = null): static
    {
        $this->limit->setLimit($limit);

        if (null !== $offset) {
            $this->limit->setOffset($offset);
        }

        return $this;
    }

    /**
     * Offset.
     *
     * @param int $offset
     *
     * @return static
     */
    public function offset(int $offset): static
    {
        $this->limit->setOffset($offset);

        return $this;
    }
}
