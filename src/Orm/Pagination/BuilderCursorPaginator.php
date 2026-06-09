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

use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Query\Builder;
use Hector\Query\Pagination\QueryCursorPaginator;
use Hector\Query\QueryBuilder;

/**
 * Entity keyset (cursor) pagination.
 *
 * See {@see QueryCursorPaginator} for the requirements and limitations of cursor
 * pagination — in particular, the `ORDER BY` must define a total order (use the
 * primary key as a tie-breaker) and must not order by a MySQL `ENUM` column.
 *
 * @template T of Entity
 * @extends QueryCursorPaginator<T>
 */
class BuilderCursorPaginator extends QueryCursorPaginator
{
    use OptimizedFetchTrait;

    public function __construct(
        Builder $builder,
        bool $withTotal = true,
        bool $optimized = false,
    ) {
        $this->optimized = $optimized;
        parent::__construct($builder, $withTotal);
    }

    /**
     * @inheritDoc
     */
    protected function fetchItems(QueryBuilder $builder): array
    {
        if (true === $this->optimized) {
            return $this->fetchItemsOptimized($builder);
        }

        /** @var Builder $builder */
        return $builder->all()->getArrayCopy();
    }

    /**
     * @inheritDoc
     */
    protected function extractCursorPosition(array|object $item, array $orderColumns): array
    {
        if ($item instanceof Entity) {
            $originalData = ReflectionEntity::get($item)
                ->getHectorData($item)
                ->get('original', []);

            $position = [];
            foreach ($orderColumns as $orderItem) {
                $key = $this->normalizeColumnKey($orderItem['column']);
                $position[$key] = $originalData[$key] ?? null;
            }

            return $position;
        }

        return parent::extractCursorPosition($item, $orderColumns);
    }
}
