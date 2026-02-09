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

use Hector\Pagination\CursorPagination;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Query\QueryBuilder;
use InvalidArgumentException;

/**
 * @template T of array
 * @extends AbstractQueryPaginator<T>
 */
class QueryCursorPaginator extends AbstractQueryPaginator
{
    /**
     * @inheritDoc
     */
    public function paginate(PaginationRequestInterface $request): CursorPagination
    {
        if (!$request instanceof CursorPaginationRequest) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', CursorPaginationRequest::class, $request::class)
            );
        }

        return $this->cursorPaginate($request);
    }

    /**
     * @return CursorPagination<T>
     */
    protected function cursorPaginate(CursorPaginationRequest $request): CursorPagination
    {
        $orderColumns = $this->extractOrderColumns();

        if (empty($orderColumns)) {
            throw new InvalidArgumentException(
                'Cursor pagination requires at least one ORDER BY clause'
            );
        }

        $countBuilder = $this->withTotal ? clone $this->builder : null;
        $builder = clone $this->builder;
        $position = $request->getPosition();
        $isBackward = $request->isBackward();

        if (null !== $position && !$this->isPositionValid($orderColumns, $position)) {
            $position = null;
        }

        // For backward navigation without a valid position, fallback to forward first page
        if ($isBackward && null === $position) {
            $isBackward = false;
        }

        if (null !== $position) {
            if ($isBackward) {
                // Backward: reverse operators to seek in opposite direction
                $this->applyCursorConditions($builder, $orderColumns, $position, reverse: true);
                // Reverse ORDER BY to get nearest items before cursor
                $this->reverseOrderBy($builder, $orderColumns);
            } else {
                $this->applyCursorConditions($builder, $orderColumns, $position);
            }
        }

        $items = $this->fetchItems($builder->limit($request->getLimit() + 1));

        $hasMore = count($items) > $request->getLimit();
        if ($hasMore) {
            array_pop($items);
        }

        // For backward navigation, re-sort items in original order
        if ($isBackward) {
            $items = array_reverse($items);
        }

        $nextPosition = null;
        $previousPosition = null;

        if (!empty($items)) {
            if ($isBackward) {
                // Going backward: there are always more items forward (we came from there)
                $nextPosition = $this->extractCursorPosition(end($items), $orderColumns);
                // There is a previous page only if we got more items than requested
                if ($hasMore) {
                    $previousPosition = $this->extractCursorPosition(reset($items), $orderColumns);
                }
            } else {
                if ($hasMore) {
                    $nextPosition = $this->extractCursorPosition(end($items), $orderColumns);
                }
                if (null !== $position) {
                    $previousPosition = $this->extractCursorPosition(reset($items), $orderColumns);
                }
            }
        }

        return new CursorPagination(
            items: $items,
            perPage: $request->getLimit(),
            nextPosition: $nextPosition,
            previousPosition: $previousPosition,
            total: $this->withTotal ? fn(): int => $countBuilder->count() : null,
        );
    }

    /**
     * Extract order columns from builder.
     *
     * @return array<array{column: string, order: string}>
     */
    protected function extractOrderColumns(): array
    {
        $columns = [];

        foreach ($this->builder->order->getOrder() as $orderItem) {
            if (!is_string($orderItem['column'])) {
                continue;
            }

            $columns[] = [
                'column' => $orderItem['column'],
                'order' => strtoupper($orderItem['order'] ?? 'ASC'),
            ];
        }

        return $columns;
    }

    /**
     * Apply cursor conditions to builder.
     *
     * @param array<array{column: string, order: string}> $orderColumns
     * @param array<string, mixed> $position
     * @param bool $reverse Reverse operators (for backward navigation)
     */
    protected function applyCursorConditions(
        QueryBuilder $builder,
        array $orderColumns,
        array $position,
        bool $reverse = false,
    ): void {
        $normalizeColumnKey = fn(string $column) => $this->normalizeColumnKey($column);
        $builder->where(function ($where) use ($normalizeColumnKey, $orderColumns, $position, $reverse) {
            foreach ($orderColumns as $i => $orderItem) {
                $column = $orderItem['column'];
                $columnKey = $normalizeColumnKey($column);
                $isDesc = $orderItem['order'] === 'DESC';
                $operator = ($isDesc xor $reverse) ? '<' : '>';
                $value = $position[$columnKey];

                $where->orWhere(function ($w) use (
                    $normalizeColumnKey,
                    $orderColumns,
                    $position,
                    $i,
                    $column,
                    $operator,
                    $value
                ) {
                    for ($j = 0; $j < $i; $j++) {
                        $prevKey = $normalizeColumnKey($orderColumns[$j]['column']);
                        $w->where($orderColumns[$j]['column'], '=', $position[$prevKey]);
                    }
                    $w->where($column, $operator, $value);
                });
            }
        });
    }

    /**
     * Reverse ORDER BY on builder for backward navigation.
     *
     * @param QueryBuilder $builder
     * @param array<array{column: string, order: string}> $orderColumns
     */
    protected function reverseOrderBy(QueryBuilder $builder, array $orderColumns): void
    {
        $builder->resetOrder();

        foreach ($orderColumns as $orderItem) {
            $builder->orderBy(
                $orderItem['column'],
                $orderItem['order'] === 'DESC' ? 'ASC' : 'DESC',
            );
        }
    }

    /**
     * Extract cursor position from item.
     *
     * @param array|object $item
     * @param array<array{column: string, order: string}> $orderColumns
     *
     * @return array<string, mixed>
     */
    protected function extractCursorPosition(array|object $item, array $orderColumns): array
    {
        $position = [];

        foreach ($orderColumns as $orderItem) {
            $key = $this->normalizeColumnKey($orderItem['column']);
            $position[$key] = is_array($item)
                ? ($item[$key] ?? null)
                : ($item->$key ?? null);
        }

        return $position;
    }

    /**
     * Validate cursor position against order columns.
     *
     * @param array<array{column: string, order: string}> $orderColumns
     * @param array<string, mixed> $position
     */
    protected function isPositionValid(array $orderColumns, array $position): bool
    {
        foreach ($orderColumns as $orderItem) {
            $key = $this->normalizeColumnKey($orderItem['column']);

            if (!array_key_exists($key, $position)) {
                return false;
            }

            $value = $position[$key];

            if (null !== $value && !is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
