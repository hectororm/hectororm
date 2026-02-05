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

namespace Hector\Pagination\View;

use Hector\Pagination\Navigator\PaginationNavigatorInterface;
use Hector\Pagination\PaginationInterface;
use Psr\Http\Message\UriInterface;

final class PaginationView
{
    public function __construct(
        private ?int $start,
        private ?int $end,
        private int $count,
        private ?int $total,
        private ?UriInterface $firstUri,
        private ?UriInterface $previousUri,
        private ?UriInterface $nextUri,
        private ?UriInterface $lastUri,
    ) {
    }

    public static function createFromNavigator(
        PaginationNavigatorInterface $navigator,
        PaginationInterface $pagination,
        UriInterface $baseUri,
    ): self {
        $current = $navigator->getCurrentRequest();

        return new self(
            start: $current?->getOffset(),
            end: $current !== null ? $current->getOffset() + $pagination->count() - 1 : null,
            count: $pagination->count(),
            total: $pagination->getTotal(),
            firstUri: $navigator->getFirstUri($baseUri),
            previousUri: $navigator->getPreviousUri($baseUri),
            nextUri: $navigator->getNextUri($baseUri),
            lastUri: $navigator->getLastUri($baseUri),
        );
    }

    /**
     * Get start.
     *
     * @return int|null
     */
    public function getStart(): ?int
    {
        return $this->start;
    }

    /**
     * Get end.
     *
     * @return int|null
     */
    public function getEnd(): ?int
    {
        return $this->end;
    }

    /**
     * Get count.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get total.
     *
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * Get first uri.
     *
     * @return UriInterface|null
     */
    public function getFirstUri(): ?UriInterface
    {
        return $this->firstUri;
    }

    /**
     * Get previous uri.
     *
     * @return UriInterface|null
     */
    public function getPreviousUri(): ?UriInterface
    {
        return $this->previousUri;
    }

    /**
     * Get next uri.
     *
     * @return UriInterface|null
     */
    public function getNextUri(): ?UriInterface
    {
        return $this->nextUri;
    }

    /**
     * Get last uri.
     *
     * @return UriInterface|null
     */
    public function getLastUri(): ?UriInterface
    {
        return $this->lastUri;
    }

    public function hasPosition(): bool
    {
        return $this->start !== null;
    }

    public function hasTotal(): bool
    {
        return $this->total !== null;
    }
}
