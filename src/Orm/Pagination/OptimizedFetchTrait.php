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

        // Build the subquery: SELECT DISTINCT pk FROM ... WHERE ... ORDER BY ... LIMIT ...
        $idsSubQuery = clone $builder;
        $idsSubQuery->resetColumns()->distinct();
        foreach ($pkColumnNames as $col) {
            $idsSubQuery->column(new Quoted(Builder::FROM_ALIAS . '.' . $col));
        }

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
}
