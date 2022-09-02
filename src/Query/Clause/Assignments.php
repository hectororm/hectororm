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

trait Assignments
{
    public Component\Assignments $assignments;

    /**
     * Reset assignments.
     *
     * @return static
     */
    public function resetAssignments(): static
    {
        $this->assignments = new Component\Assignments($this);

        return $this;
    }

    /**
     * Assignment.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function assign(Closure|StatementInterface|string $column, mixed $value): static
    {
        $this->assignments->assignment($column, $value);

        return $this;
    }

    /**
     * Assignments.
     *
     * @param array|StatementInterface $values
     *
     * @return static
     */
    public function assigns(array|StatementInterface $values): static
    {
        $this->assignments->assignments($values);

        return $this;
    }
}