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

namespace Hector\Pagination\Navigator;

use Hector\Pagination\Request\PaginationRequestInterface;
use Psr\Http\Message\UriInterface;

interface PaginationNavigatorInterface
{
    /**
     * Get current pagination request (represents current position).
     * Returns null for cursor-based pagination (no absolute position).
     *
     * @return PaginationRequestInterface|null
     */
    public function getCurrentRequest(): ?PaginationRequestInterface;

    /**
     * Get first pagination request.
     *
     * @return PaginationRequestInterface|null
     */
    public function getFirstRequest(): ?PaginationRequestInterface;

    /**
     * Get last pagination request.
     *
     * @return PaginationRequestInterface|null
     */
    public function getLastRequest(): ?PaginationRequestInterface;

    /**
     * Get previous pagination request.
     *
     * @return PaginationRequestInterface|null
     */
    public function getPreviousRequest(): ?PaginationRequestInterface;

    /**
     * Get next pagination request.
     *
     * @return PaginationRequestInterface|null
     */
    public function getNextRequest(): ?PaginationRequestInterface;

    /**
     * Get URI for first page.
     */
    public function getFirstUri(UriInterface $baseUri): ?UriInterface;

    /**
     * Get URI for last page.
     */
    public function getLastUri(UriInterface $baseUri): ?UriInterface;

    /**
     * Get URI for previous page.
     */
    public function getPreviousUri(UriInterface $baseUri): ?UriInterface;

    /**
     * Get URI for next page.
     */
    public function getNextUri(UriInterface $baseUri): ?UriInterface;
}
