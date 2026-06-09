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

namespace Hector\Orm\Pagination;

use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Query\Builder;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Expression;
use Hector\Query\Statement\Quoted;

/**
 * Provides optimized pagination for ORM Builder paginators.
 *
 * When enabled, the paginator uses a derived table INNER JOIN to select
 * distinct primary keys with the original WHERE, JOINs, ORDER BY and LIMIT:
 *
 * ```sql
 * SELECT main.*
 * FROM entity AS main
 * INNER JOIN (
 *     SELECT DISTINCT main.pk FROM entity AS main
 *     JOIN … WHERE … ORDER BY … LIMIT …
 * ) AS pagination ON (main.pk = pagination.pk)
 * ORDER BY …
 * ```
 *
 * This prevents row duplication caused by JOINs from affecting the per-page count,
 * using a single SQL query.
 */
trait OptimizedFetchTrait
{
    private bool $optimized = false;

    /**
     * Fetch items using optimized derived-table pagination.
     *
     * @param QueryBuilder $builder The cloned builder with LIMIT/OFFSET/cursor already applied.
     *
     * @return array
     */
    protected function fetchItemsOptimized(QueryBuilder $builder): array
    {
        /** @var Builder $builder */
        $reflection = ReflectionEntity::get($builder->getEntityClass());
        $primaryIndex = $reflection->getPrimaryIndex();

        // No primary key: fallback to standard fetch
        if (null === $primaryIndex) {
            return $builder->all()->getArrayCopy();
        }

        $pkColumnNames = $primaryIndex->getColumnsName();

        $idsSubQuery = $this->buildDistinctIdsSubQuery($builder, $pkColumnNames);

        // Build the outer query: SELECT main.* FROM entity AS main
        // INNER JOIN (subquery) AS pagination ON (main.pk = pagination.pk)
        // ORDER BY ...
        $entityBuilder = new Builder($reflection->class);
        $entityBuilder->with = $builder->with;

        // Build ON condition: main.pk = pagination.pk (for each PK column)
        $onConditions = [];
        foreach ($pkColumnNames as $col) {
            $onConditions[] = new Expression(
                new Quoted(Builder::FROM_ALIAS . '.' . $col),
                ' = ',
                new Quoted('pagination.' . $col),
            );
        }

        $entityBuilder->innerJoin($idsSubQuery, $onConditions, 'pagination');

        // Copy ORDER BY from the original builder
        $entityBuilder->order = clone $builder->order;
        $entityBuilder->order->builder = $entityBuilder;

        return $entityBuilder->all()->getArrayCopy();
    }

    /**
     * Count distinct primary keys for optimized pagination.
     *
     * JOINs (e.g. one-to-many) would otherwise inflate `COUNT(*)`. Counting the
     * `SELECT DISTINCT pk` subquery yields the real number of matching entities.
     * Falls back to the default row count when the entity has no primary key.
     *
     * @param QueryBuilder $builder
     *
     * @return int
     */
    protected function fetchTotal(QueryBuilder $builder): int
    {
        if (false === $this->optimized) {
            return parent::fetchTotal($builder);
        }

        /** @var Builder $builder */
        $primaryIndex = ReflectionEntity::get($builder->getEntityClass())->getPrimaryIndex();

        // No primary key: nothing to deduplicate on, keep the default count.
        if (null === $primaryIndex) {
            return parent::fetchTotal($builder);
        }

        // Count only distinct primary keys: do NOT add ORDER BY columns to the SELECT
        // list here, as they could make otherwise-identical PK rows distinct and
        // re-inflate the count.
        return $this->buildDistinctIdsSubQuery($builder, $primaryIndex->getColumnsName(), false)->count();
    }

    /**
     * Build the `SELECT DISTINCT main.<pk>[, order columns]` subquery used both to
     * fetch the page items and to count distinct matching entities.
     *
     * @param Builder $builder
     * @param string[] $pkColumnNames
     * @param bool $withOrderColumns Whether to add column ORDER BY items to the SELECT
     *                               list (required for the items fetch under
     *                               ONLY_FULL_GROUP_BY; must be false when counting).
     *
     * @return Builder
     */
    private function buildDistinctIdsSubQuery(
        QueryBuilder $builder,
        array $pkColumnNames,
        bool $withOrderColumns = true,
    ): Builder {
        /** @var Builder $builder */
        $idsSubQuery = clone $builder;
        $idsSubQuery->resetColumns()->distinct();

        foreach ($pkColumnNames as $col) {
            $idsSubQuery->column(new Quoted(Builder::FROM_ALIAS . '.' . $col));
        }

        if (false === $withOrderColumns) {
            return $idsSubQuery;
        }

        // Ensure ORDER BY columns appear in the DISTINCT SELECT list (SQL standard /
        // MySQL ONLY_FULL_GROUP_BY, same constraint on PostgreSQL/Oracle). The PK
        // already makes each row unique, so adding the (deterministic) sort columns
        // does not change which rows DISTINCT keeps. Read the order from the passed
        // builder (cursor backward navigation may have reversed it).
        foreach ($this->extractColumnOrderItems($builder->order) as $item) {
            $column = $item['column'];

            // Skip primary key column(s): already selected as main.<pk>.
            if (is_string($column) && in_array($this->normalizeColumnKey($column), $pkColumnNames, true)) {
                continue;
            }

            $idsSubQuery->column($column);
        }

        return $idsSubQuery;
    }
}
