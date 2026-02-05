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

use Hector\Query\QueryBuilder;

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
     * Normalize column name to key.
     */
    protected function normalizeColumnKey(string $column): string
    {
        $column = str_replace('`', '', $column);

        if (str_contains($column, '.')) {
            $column = substr($column, strrpos($column, '.') + 1);
        }

        return $column;
    }
}
