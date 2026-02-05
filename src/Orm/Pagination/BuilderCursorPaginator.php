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
use Hector\Pagination\Encoder\CursorEncoderInterface;
use Hector\Query\Pagination\QueryCursorPaginator;
use Hector\Query\QueryBuilder;

/**
 * @template T of Entity
 * @extends QueryCursorPaginator<T>
 */
class BuilderCursorPaginator extends QueryCursorPaginator
{
    public function __construct(
        Builder $builder,
        bool $withTotal = true,
    ) {
        parent::__construct($builder, $withTotal);
    }

    /**
     * @inheritDoc
     */
    protected function fetchItems(QueryBuilder $builder): array
    {
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
