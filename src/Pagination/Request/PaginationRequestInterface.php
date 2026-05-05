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

namespace Hector\Pagination\Request;

interface PaginationRequestInterface
{
    /**
     * Get SQL LIMIT value.
     *
     * @return int
     */
    public function getLimit(): int;

    /**
     * Get SQL OFFSET value.
     *
     * @return int
     */
    public function getOffset(): int;

    /**
     * Return a copy of this request with the given per-page value.
     *
     * The new instance must preserve all other request parameters
     * (page/position/start/direction). Implementations are immutable.
     *
     * @param int $perPage New per-page value (must be >= 1)
     *
     * @return static
     */
    public function withPerPage(int $perPage): static;
}
