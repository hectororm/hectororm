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

/**
 * Cursor-based (keyset) pagination.
 *
 * @template T
 * @extends PaginationInterface<T>
 */
interface CursorPaginationInterface extends PaginationInterface
{
    /**
     * Get position for next page.
     * Returns null if no more items.
     *
     * @return array<string, mixed>|null
     */
    public function getNextPosition(): ?array;

    /**
     * Get position for previous page.
     * Returns null if on first page.
     *
     * @return array<string, mixed>|null
     */
    public function getPreviousPosition(): ?array;

    /**
     * Get named cursor identifier (for server-side stored cursors).
     * Returns null if cursor is not stored.
     *
     * @return string|null
     */
    public function getCursorName(): ?string;
}
