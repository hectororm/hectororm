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
 * @implements RangePaginationInterface<T>
 */
class RangePagination extends AbstractPagination implements RangePaginationInterface
{
    /**
     * @param iterable<T> $items
     */
    public function __construct(
        iterable $items,
        private int $start,
        private int $end,
        Closure|int|null $total = null,
    ) {
        if ($start < 0) {
            throw new InvalidArgumentException('start must be >= 0');
        }
        if ($end < $start) {
            throw new InvalidArgumentException('end must be >= start');
        }
        parent::__construct(
            items: $items,
            perPage: $this->end - $this->start + 1,
            total: $total,
        );
    }

    /**
     * @inheritDoc
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * @inheritDoc
     */
    public function getEnd(): int
    {
        return $this->end;
    }

    /**
     * @inheritDoc
     */
    public function hasMore(): bool
    {
        if (null === $this->getTotal()) {
            // Unknown total: assume more if we got full range
            return $this->count() >= $this->perPage;
        }

        return $this->end < ($this->getTotal() - 1);
    }

    /**
     * @inheritDoc
     */
    public function hasPrevious(): bool
    {
        return $this->start > 0;
    }

    /**
     * @inheritDoc
     */
    public function getContentRange(string $unit = 'items'): string
    {
        $total = $this->getTotal() ?? '*';

        return sprintf('%s %d-%d/%s', $unit, $this->start, $this->end, $total);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->getArrayCopy(),
            'start' => $this->start,
            'end' => $this->end,
            'per_page' => $this->perPage,
            'total' => $this->getTotal(),
            'has_more' => $this->hasMore(),
        ];
    }
}
