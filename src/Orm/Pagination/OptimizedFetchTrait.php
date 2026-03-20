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
use Hector\Query\Statement\Quoted;
use Hector\Query\Statement\Row;

/**
 * Provides optimized 2-step pagination for ORM Builder paginators.
 *
 * When enabled, the paginator executes two queries:
 *
 * 1. `SELECT DISTINCT pk FROM entity JOIN … WHERE … ORDER BY … LIMIT …`
 *    → fetches only the paginated primary key values (fast, no ORM mapping)
 *
 * 2. `SELECT * FROM entity WHERE pk IN (…)`
 *    → loads full entities for those IDs only (no duplicate JOINs)
 *
 * Results are reordered in PHP to match the original ORDER BY from step 1.
 *
 * This prevents row duplication caused by JOINs from affecting the per-page count.
 */
trait OptimizedFetchTrait
{
    private bool $optimized = false;

    /**
     * Fetch items using optimized 2-step pagination.
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

        // Step 1: SELECT DISTINCT pk FROM ... WHERE ... ORDER BY ... LIMIT ...
        $idsBuilder = clone $builder;
        $idsBuilder->resetColumns()->distinct();
        foreach ($pkColumnNames as $col) {
            $idsBuilder->column(new Quoted(Builder::FROM_ALIAS . '.' . $col));
        }
        $rows = iterator_to_array($idsBuilder->fetchAll());

        if (empty($rows)) {
            return [];
        }

        // Extract PK values from rows
        $pkValues = array_map(fn(array $row): array => array_values($row), $rows);

        // Step 2: SELECT * FROM entity WHERE pk IN (...) — no JOINs needed
        $entityBuilder = new Builder($reflection->class);
        $entityBuilder->with = $builder->with;

        $pkQuoted = array_map(
            fn(string $name): Quoted => new Quoted(Builder::FROM_ALIAS . '.' . $name),
            $pkColumnNames,
        );

        if (1 === count($pkQuoted)) {
            $entityBuilder->whereIn(reset($pkQuoted), array_column($pkValues, 0));
        } else {
            $entityBuilder->whereIn(new Row(...$pkQuoted), $pkValues);
        }

        $entities = $entityBuilder->all()->getArrayCopy();

        // Reorder entities to match the order from step 1
        return $this->reorderByPrimaryKeys($entities, $pkValues, $pkColumnNames, $reflection);
    }

    /**
     * Reorder entities to match the primary key order from the ID query.
     *
     * @param array $entities Hydrated entities (unordered).
     * @param array $pkValues Ordered PK values from step 1.
     * @param array $pkColumnNames PK column names.
     * @param ReflectionEntity $reflection
     *
     * @return array Entities in the same order as $pkValues.
     */
    private function reorderByPrimaryKeys(
        array $entities,
        array $pkValues,
        array $pkColumnNames,
        ReflectionEntity $reflection,
    ): array {
        // Index entities by their PK signature
        $indexed = [];
        foreach ($entities as $entity) {
            $originalData = $reflection->getHectorData($entity)->get('original', []);
            $key = implode("\0", array_map(fn(string $col) => (string)($originalData[$col] ?? ''), $pkColumnNames));
            $indexed[$key] = $entity;
        }

        // Rebuild array in step 1 order
        $ordered = [];
        foreach ($pkValues as $pk) {
            $key = implode("\0", array_map(fn($v) => (string)$v, $pk));

            if (isset($indexed[$key])) {
                $ordered[] = $indexed[$key];
            }
        }

        return $ordered;
    }
}
