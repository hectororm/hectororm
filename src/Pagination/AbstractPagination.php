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

namespace Hector\Pagination;

use ArrayIterator;
use Closure;
use InvalidArgumentException;
use Traversable;

/**
 * @template T
 * @implements PaginationInterface<T>
 */
abstract class AbstractPagination implements PaginationInterface
{
    /** @var iterable<T> */
    protected iterable $items;
    /** @var array<int, T>|null */
    private ?array $resolvedItems = null;
    private ?int $resolvedTotal = null;

    /**
     * @param iterable<T> $items
     * @param int|Closure(): int|null $total
     */
    public function __construct(
        iterable $items,
        protected int $perPage,
        protected int|Closure|null $total = null,
    ) {
        if ($perPage <= 0) {
            throw new InvalidArgumentException('perPage must be greater than 0');
        }

        $this->items = $items;
    }

    /**
     * @inheritDoc
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        if ($this->items instanceof Traversable) {
            /** @var Traversable<int, T> */
            return $this->items;
        }

        return new ArrayIterator($this->items);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        if (is_countable($this->items)) {
            return count($this->items);
        }

        return count($this->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        if (null === $this->resolvedItems) {
            $this->resolvedItems = $this->items instanceof Traversable
                ? iterator_to_array($this->items, false)
                : array_values($this->items);
        }

        return $this->resolvedItems;
    }

    /**
     * @inheritDoc
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): ?int
    {
        if (null === $this->total) {
            return null;
        }

        if (null === $this->resolvedTotal) {
            $this->resolvedTotal = match ($this->total instanceof Closure) {
                true => ($this->total)(),
                false => $this->total,
            };
        }

        return $this->resolvedTotal;
    }
}
