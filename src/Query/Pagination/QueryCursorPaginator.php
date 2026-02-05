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
use Hector\Pagination\Encoder\CursorEncoderInterface;
use Hector\Pagination\PaginationInterface;
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

        if (null !== $position) {
            $this->applyCursorConditions($builder, $orderColumns, $position);
        }

        $items = $this->fetchItems($builder->limit($request->getLimit() + 1));

        $hasMore = count($items) > $request->getLimit();
        if ($hasMore) {
            array_pop($items);
        }

        $nextPosition = null;
        $previousPosition = null;

        if (!empty($items)) {
            if ($hasMore) {
                $nextPosition = $this->extractCursorPosition(end($items), $orderColumns);
            }
            if (null !== $position) {
                $previousPosition = $this->extractCursorPosition(reset($items), $orderColumns);
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
     */
    protected function applyCursorConditions(
        QueryBuilder $builder,
        array $orderColumns,
        array $position,
    ): void {
        $orderColumns = array_values(array_filter(
            $orderColumns,
            fn($col) => array_key_exists($this->normalizeColumnKey($col['column']), $position)
        ));

        if (empty($orderColumns)) {
            return;
        }

        $normalizeColumnKey = fn(string $column) => $this->normalizeColumnKey($column);
        $builder->where(function ($where) use ($normalizeColumnKey, $orderColumns, $position) {
            foreach ($orderColumns as $i => $orderItem) {
                $column = $orderItem['column'];
                $columnKey = $normalizeColumnKey($column);
                $operator = $orderItem['order'] === 'DESC' ? '<' : '>';
                $value = $position[$columnKey];

                $where->orWhere(function ($w) use ($normalizeColumnKey, $orderColumns, $position, $i, $column, $operator, $value) {
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
}
