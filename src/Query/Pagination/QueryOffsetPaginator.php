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
        $bounds = $this->getBuilderBounds();
        $baseOffset = $bounds->getOffset() ?? 0;
        $builderLimit = $bounds->getLimit();
        $requestLimit = $request->getLimit();

        $countBuilder = $this->withTotal ? clone $this->builder : null;

        // Compute effective SQL limit, bounded by user-defined limit.
        // When remaining items in the window exceed requestLimit, add +1 for
        // hasMore detection (N+1 trick). Otherwise, use remaining as-is since
        // we already know we are at the end of the window.
        if (null !== $builderLimit) {
            $remaining = max(0, $builderLimit - $request->getOffset());
            $effectiveLimit = min($requestLimit + 1, $remaining);
        } else {
            $effectiveLimit = $requestLimit + 1;
        }

        $items = $this->fetchItems(
            (clone $this->builder)
                ->limit($effectiveLimit, $baseOffset + $request->getOffset())
        );

        $hasMore = count($items) > $requestLimit;
        if (null !== $builderLimit && $request->getOffset() + $requestLimit >= $builderLimit) {
            $hasMore = false;
        }
        if ($hasMore) {
            array_pop($items);
        }

        return new OffsetPagination(
            items: $items,
            perPage: $requestLimit,
            currentPage: $this->calculateCurrentPage($request->getOffset(), $requestLimit),
            hasMore: $hasMore,
            total: true === $this->withTotal
                ? fn(): int => $this->boundTotal($countBuilder->count(), $bounds)
                : null,
        );
    }
}
