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

interface OffsetPaginationInterface extends PaginationInterface
{
    /**
     * Current page (1-based).
     *
     * @return int
     */
    public function getCurrentPage(): int;

    /**
     * Current offset (0-based).
     *
     * @return int
     */
    public function getOffset(): int;

    /**
     * Get total number of pages.
     * Returns null if total is unknown.
     *
     * @return int|null
     */
    public function getTotalPages(): ?int;

    /**
     * Get first item number on current page (1-based).
     * Returns null if empty or total unknown.
     *
     * @return int|null
     */
    public function getFirstItem(): ?int;

    /**
     * Get last item number on current page (1-based).
     * Returns null if empty.
     *
     * @return int|null
     */
    public function getLastItem(): ?int;
}
