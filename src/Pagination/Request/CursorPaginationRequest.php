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

use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Encoder\CursorEncoderInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CursorPaginationRequest implements PaginationRequestInterface
{
    public const DEFAULT_PER_PAGE = 15;

    /**
     * @param array<string, mixed>|null $position Decoded cursor position
     */
    public function __construct(
        private int $perPage = self::DEFAULT_PER_PAGE,
        private ?array $position = null,
    ) {
    }

    /**
     * Create from encoded cursor string.
     */
    public static function fromCursor(
        ?string $cursor,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?CursorEncoderInterface $encoder = null,
    ): self {
        $position = null;

        if (null !== $cursor && '' !== $cursor) {
            $encoder ??= new Base64CursorEncoder();
            $position = $encoder->decode($cursor);
        }

        return new self($perPage, $position);
    }

    /**
     * Create from request.
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        string $cursorParam = 'cursor',
        ?string $perPageParam = 'per_page',
        int $defaultPerPage = self::DEFAULT_PER_PAGE,
        int|false $maxPerPage = false,
        ?CursorEncoderInterface $encoder = null,
    ): self {
        $query = $request->getQueryParams();
        $cursor = $query[$cursorParam] ?? null ?: null;

        // If perPage is locked (maxPerPage is false), use default
        if (false === $maxPerPage || null === $perPageParam) {
            return self::fromCursor($cursor, $defaultPerPage, $encoder);
        }

        $perPage = min($maxPerPage, max(1, (int)($query[$perPageParam] ?? $defaultPerPage)));

        return self::fromCursor($cursor, $perPage, $encoder);
    }

    /**
     * Get decoded cursor position.
     *
     * @return array<string, mixed>|null
     */
    public function getPosition(): ?array
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): int
    {
        return $this->perPage;
    }
}
