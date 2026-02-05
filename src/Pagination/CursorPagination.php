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

/**
 * @template T
 * @extends AbstractPagination<T>
 * @implements CursorPaginationInterface<T>
 */
class CursorPagination extends AbstractPagination implements CursorPaginationInterface
{
    /**
     * @param iterable<T> $items
     * @param array<string, mixed>|null $nextPosition Position for next page
     * @param array<string, mixed>|null $previousPosition Position for previous page
     * @param int|Closure(): int|null $total Total count (optional, can be lazy)
     */
    public function __construct(
        iterable $items,
        int $perPage,
        private ?array $nextPosition = null,
        private ?array $previousPosition = null,
        private ?string $cursorName = null,
        int|Closure|null $total = null,
    ) {
        parent::__construct(
            items: $items,
            perPage: $perPage,
            total: $total,
        );
    }

    /**
     * @inheritDoc
     */
    public function getNextPosition(): ?array
    {
        return $this->nextPosition;
    }

    /**
     * @inheritDoc
     */
    public function getPreviousPosition(): ?array
    {
        return $this->previousPosition;
    }

    /**
     * @inheritDoc
     */
    public function hasMore(): bool
    {
        return null !== $this->nextPosition;
    }

    /**
     * @inheritDoc
     */
    public function hasPrevious(): bool
    {
        return null !== $this->previousPosition;
    }

    /**
     * @inheritDoc
     */
    public function getCursorName(): ?string
    {
        return $this->cursorName;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data = [
            'data' => $this->getArrayCopy(),
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore(),
            'next_position' => $this->nextPosition,
            'previous_position' => $this->previousPosition,
        ];

        if (null !== $this->cursorName) {
            $data['cursor_name'] = $this->cursorName;
        }

        $total = $this->getTotal();
        if (null !== $total) {
            $data['total'] = $total;
        }

        return $data;
    }
}
