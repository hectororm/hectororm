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

use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
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
        $bounds = $this->getBuilderBounds();
        $baseOffset = $bounds->getOffset() ?? 0;
        $builderLimit = $bounds->getLimit();

        $countBuilder = $this->withTotal ? clone $this->builder : null;

        // Compute effective SQL limit, bounded by user-defined limit
        if (null !== $builderLimit) {
            $remaining = max(0, $builderLimit - $request->getOffset());
            $effectiveLimit = min($request->getLimit(), $remaining);
        } else {
            $effectiveLimit = $request->getLimit();
        }

        $items = $this->fetchItems(
            (clone $this->builder)
                ->limit($effectiveLimit, $baseOffset + $request->getOffset())
        );

        return new RangePagination(
            items: $items,
            start: $request->getOffset(),
            end: $request->getOffsetEnd(),
            total: $this->withTotal
                ? $this->boundTotal($countBuilder->count(), $bounds)
                : null,
        );
    }
}
