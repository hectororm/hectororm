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

use Closure;
use InvalidArgumentException;

/**
 * @template T
 * @extends AbstractPagination<T>
 * @implements OffsetPaginationInterface<T>
 */
class OffsetPagination extends AbstractPagination implements OffsetPaginationInterface
{
    /**
     * @param iterable<T> $items
     * @param int|Closure(): int|null $total
     */
    public function __construct(
        iterable $items,
        int $perPage,
        private int $currentPage = 1,
        private ?bool $hasMore = null,
        int|Closure|null $total = null,
    ) {
        if ($currentPage < 1) {
            throw new InvalidArgumentException('currentPage must be at least 1');
        }

        parent::__construct($items, $perPage, $total);
    }

    /**
     * @inheritDoc
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /**
     * @inheritDoc
     */
    public function getTotalPages(): ?int
    {
        $total = $this->getTotal();

        if (null === $total) {
            return null;
        }

        return (int)ceil($total / $this->perPage);
    }

    /**
     * @inheritDoc
     */
    public function getFirstItem(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->getOffset() + 1;
    }

    /**
     * @inheritDoc
     */
    public function getLastItem(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->getOffset() + $this->count();
    }

    /**
     * @inheritDoc
     */
    public function hasMore(): bool
    {
        if (null !== $this->hasMore) {
            return $this->hasMore;
        }

        $total = $this->getTotal();
        if (null !== $total) {
            return ($this->currentPage * $this->perPage) < $total;
        }

        // Fallback: assume more if we got a full page
        return $this->count() >= $this->perPage;
    }

    /**
     * @inheritDoc
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data = [
            'data' => $this->getArrayCopy(),
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'has_more' => $this->hasMore(),
        ];

        $total = $this->getTotal();
        if (null !== $total) {
            $data['total'] = $total;
            $data['total_pages'] = $this->getTotalPages();
            $data['first_item'] = $this->getFirstItem();
            $data['last_item'] = $this->getLastItem();
        }

        return $data;
    }
}
