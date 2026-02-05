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

use Psr\Http\Message\ServerRequestInterface;

final class OffsetPaginationRequest implements PaginationRequestInterface
{
    public const DEFAULT_PER_PAGE = 15;

    public function __construct(
        private int $page = 1,
        private int $perPage = self::DEFAULT_PER_PAGE,
    ) {
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
            return new self($page, $defaultPerPage);
        }

        $perPage = min($maxPerPage, max(1, (int)($query[$perPageParam] ?? $defaultPerPage)));

        return new self($page, $perPage);
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
}
