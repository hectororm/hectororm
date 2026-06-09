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

namespace Hector\Query\Pagination;

use Hector\Query\Component\Limit;
use Hector\Query\Component\Order;
use Hector\Query\Helper;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Quoted;

/**
 * @template T
 * @implements QueryPaginatorInterface<T>
 */
abstract class AbstractQueryPaginator implements QueryPaginatorInterface
{
    public function __construct(
        protected QueryBuilder $builder,
        protected bool $withTotal = true,
    ) {
    }

    /**
     * Get user-defined bounds from the builder.
     *
     * Returns a clone of the limit component that was set on the builder
     * before pagination, allowing paginators to respect offset/limit as bounds.
     */
    protected function getBuilderBounds(): Limit
    {
        return clone $this->builder->limit;
    }

    /**
     * Bound total count to user-defined limits.
     *
     * @param int $total Raw total count from the database.
     * @param Limit $bounds User-defined bounds.
     *
     * @return int
     */
    protected function boundTotal(int $total, Limit $bounds): int
    {
        $total = max(0, $total - ($bounds->getOffset() ?? 0));

        if (null !== $bounds->getLimit()) {
            $total = min($total, $bounds->getLimit());
        }

        return $total;
    }

    /**
     * Fetch items from builder.
     *
     * @param QueryBuilder $builder
     *
     * @return array<T>
     */
    protected function fetchItems(QueryBuilder $builder): array
    {
        return iterator_to_array($builder->fetchAll());
    }

    /**
     * Fetch the raw total count from the (cloned) builder.
     *
     * Extension point: subclasses can override this to count differently (e.g. count
     * distinct primary keys when JOINs would otherwise inflate the row count).
     *
     * @param QueryBuilder $builder
     *
     * @return int
     */
    protected function fetchTotal(QueryBuilder $builder): int
    {
        return $builder->count();
    }

    /**
     * Calculate current page from request.
     */
    protected function calculateCurrentPage(int $offset, int $limit): int
    {
        if ($limit === 0) {
            return 1;
        }

        return (int)floor($offset / $limit) + 1;
    }

    /**
     * Extract ORDER BY items that are plain column references (deterministic and
     * materialisable), usable both as cursor keys and inside a SELECT DISTINCT.
     *
     * Expressions/functions (RAND(), COUNT(*), ...), sub-queries and closures are
     * excluded. The order direction is normalised to upper-case.
     *
     * @param Order $order
     *
     * @return array<array{column: Quoted|string, order: string}>
     */
    protected function extractColumnOrderItems(Order $order): array
    {
        $items = [];

        foreach ($order->getOrder() as $orderItem) {
            if (false === Helper::isColumnReference($orderItem['column'])) {
                continue;
            }

            $items[] = [
                'column' => $orderItem['column'],
                'order' => strtoupper($orderItem['order'] ?? 'ASC'),
            ];
        }

        return $items;
    }

    /**
     * Normalize column name to key.
     */
    protected function normalizeColumnKey(string $column): string
    {
        $segments = Helper::explodePath($column);

        return Helper::unquote((string)end($segments)) ?? '';
    }
}
