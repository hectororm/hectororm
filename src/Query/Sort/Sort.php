<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query\Sort;

use Hector\Query\Component\Order;
use Hector\Query\QueryBuilder;

final class Sort implements SortInterface
{
    public function __construct(
        private string $column,
        private string $dir = Order::ORDER_ASC,
    ) {
    }

    /**
     * Get column.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get direction.
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @inheritDoc
     */
    public function apply(QueryBuilder $builder): void
    {
        $builder->orderBy($this->column, $this->dir);
    }
}
