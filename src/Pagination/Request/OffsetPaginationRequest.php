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

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

final class OffsetPaginationRequest implements PaginationRequestInterface
{
    public const DEFAULT_PER_PAGE = 15;

    public function __construct(
        private int $page = 1,
        private int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('page must be at least 1');
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException('perPage must be at least 1');
        }
    }

    /**
     * Create from request.
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        string $pageParam = 'page',
        ?string $perPageParam = 'per_page',
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int|false $maxPerPage = false,
    ): self {
        $query = $request->getQueryParams();

        $page = max(1, (int)($query[$pageParam] ?? 1));

        // If perPage is locked (maxPerPage is false), use default
        if (false === $maxPerPage || null === $perPageParam) {
            $perPage = max(1, $defaultPerPage);

            return new self(self::boundPage($page, $perPage), $perPage);
        }

        $perPage = min($maxPerPage, max(1, (int)($query[$perPageParam] ?? $defaultPerPage)));

        return new self(self::boundPage($page, $perPage), $perPage);
    }

    /**
     * Bound the page so that the resulting offset stays a valid (non-overflowing) int.
     *
     * Without this, a hostile `?page=PHP_INT_MAX` makes `getOffset()` overflow to a
     * float and triggers a TypeError on the `int` return type.
     *
     * @param int $page
     * @param int $perPage
     *
     * @return int
     */
    private static function boundPage(int $page, int $perPage): int
    {
        return min($page, intdiv(PHP_INT_MAX, $perPage) + 1);
    }

    /**
     * Get current page number.
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): int
    {
        return $this->perPage;
    }

    /**
     * @inheritDoc
     */
    public function withPerPage(int $perPage): static
    {
        return new self($this->page, $perPage);
    }
}
