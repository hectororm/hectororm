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

use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Query\QueryBuilder;

/**
 * @template T of array
 * @extends AbstractQueryPaginator<T>
 */
class QueryOffsetPaginator extends AbstractQueryPaginator
{
    /**
     * @inheritDoc
     */
    public function paginate(PaginationRequestInterface $request): OffsetPagination
    {
        $countBuilder = $this->withTotal ? clone $this->builder : null;

        $items = $this->fetchItems(
            (clone $this->builder)
                ->limit($request->getLimit() + 1)
                ->offset($request->getOffset())
        );

        $hasMore = count($items) > $request->getLimit();
        if ($hasMore) {
            array_pop($items);
        }

        return new OffsetPagination(
            items: $items,
            perPage: $request->getLimit(),
            currentPage: $this->calculateCurrentPage($request->getOffset(), $request->getLimit()),
            hasMore: $hasMore,
            total: true === $this->withTotal ? fn(): int => $countBuilder->count() : null,
        );
    }
}
