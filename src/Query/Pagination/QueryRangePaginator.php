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

use Hector\Pagination\PaginationInterface;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Query\QueryBuilder;
use InvalidArgumentException;

/**
 * @template T of array
 * @extends AbstractQueryPaginator<T>
 */
class QueryRangePaginator extends AbstractQueryPaginator
{
    /**
     * @inheritDoc
     */
    public function paginate(PaginationRequestInterface $request): RangePagination
    {
        if (!$request instanceof RangePaginationRequest) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', RangePaginationRequest::class, $request::class)
            );
        }

        return $this->rangePaginate($request);
    }

    /**
     * @return RangePagination<T>
     */
    protected function rangePaginate(RangePaginationRequest $request): RangePagination
    {
        $countBuilder = $this->withTotal ? clone $this->builder : null;

        $items = $this->fetchItems(
            (clone $this->builder)
                ->limit($request->getLimit())
                ->offset($request->getOffset())
        );

        return new RangePagination(
            items: $items,
            start: $request->getOffset(),
            end: $request->getOffsetEnd(),
            total: $this->withTotal ? $countBuilder->count() : null,
        );
    }
}
