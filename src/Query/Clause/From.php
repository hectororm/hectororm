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

trait From
{
    public Component\Table $from;

    /**
     * Reset from.
     *
     * @return static
     */
    public function resetFrom(): static
    {
        $this->from = new Component\Table($this);

        return $this;
    }

    /**
     * From.
     *
     * @param StatementInterface|string $table
     * @param string|null $alias
     *
     * @return static
     */
    public function from(StatementInterface|string $table, ?string $alias = null): static
    {
        $this->from->table($table, $alias);

        return $this;
    }
}
