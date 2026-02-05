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

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @template T
 * @extends IteratorAggregate<int, T>
 */
interface PaginationInterface extends IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @inheritDoc
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable;

    /**
     * Get the original items iterable.
     *
     * @return iterable<T>
     */
    public function getItems(): iterable;

    /**
     * Get array copy.
     *
     * @return array<int, T>
     */
    public function getArrayCopy(): array;

    /**
     * Get number of items per page.
     *
     * @return int
     */
    public function getPerPage(): int;

    /**
     * Get total number of items.
     * Returns null if total is unknown or not computed.
     *
     * @return int|null
     */
    public function getTotal(): ?int;

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Has more items?
     *
     * @return bool
     */
    public function hasMore(): bool;

    /**
     * Has previous items?
     *
     * @return bool
     */
    public function hasPrevious(): bool;
}
