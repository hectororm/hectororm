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
 * Range-based pagination (RFC 7233 style).
 *
 * @template T
 * @extends PaginationInterface<T>
 */
interface RangePaginationInterface extends PaginationInterface
{
    /**
     * Get start index (0-based, inclusive).
     *
     * @return int
     */
    public function getStart(): int;

    /**
     * Get end index (0-based, inclusive).
     *
     * @return int
     */
    public function getEnd(): int;

    /**
     * Get Content-Range header value.
     * Example: "items 0-19/1000" or "items 0-19/*"
     *
     * @param string $unit
     *
     * @return string
     */
    public function getContentRange(string $unit = 'items'): string;
}
