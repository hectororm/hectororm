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

final class RangePaginationRequest implements PaginationRequestInterface
{
    public function __construct(
        private int $start = 0,
        private int $end = 19,
    ) {
    }

    /**
     * Create from request query string: ?offset=0&limit=20 or ?range=0-19
     */
    public static function fromRequest(
        ServerRequestInterface $request,
        string $offsetParam = 'offset',
        string $limitParam = 'limit',
        string $rangeParam = 'range',
        int $defaultLimit = 20,
        int $maxLimit = 100,
    ): self {
        $query = $request->getQueryParams();

        // Try "range=0-19" format
        if (isset($query[$rangeParam]) && preg_match('/^(\d+)-(\d+)$/', $query[$rangeParam], $matches)) {
            $start = (int)$matches[1];
            $end = (int)$matches[2];

            // Enforce max limit
            if (($end - $start + 1) > $maxLimit) {
                $end = $start + $maxLimit - 1;
            }

            return new self($start, $end);
        }

        // Try "offset=0&limit=20" format
        $offset = max(0, (int)($query[$offsetParam] ?? 0));
        $limit = min($maxLimit, max(1, (int)($query[$limitParam] ?? $defaultLimit)));

        return new self($offset, $offset + $limit - 1);
    }

    /**
     * Create from request "Range" header (RFC 7233 style, non-standard unit).
     */
    public static function fromHeader(
        ServerRequestInterface $request,
        string $unit = 'items',
        int $defaultEnd = 19,
        int $maxRange = 100,
    ): self {
        $header = $request->getHeaderLine('Range');

        $pattern = '/^' . preg_quote($unit, '/') . '=(\d+)-(\d+)$/';
        if (preg_match($pattern, $header, $matches)) {
            $start = (int)$matches[1];
            $end = (int)$matches[2];

            if (($end - $start + 1) > $maxRange) {
                $end = $start + $maxRange - 1;
            }

            return new self($start, $end);
        }

        return new self(0, $defaultEnd);
    }

    /**
     * @inheritDoc
     */
    public function getOffset(): int
    {
        return $this->start;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): int
    {
        return $this->end - $this->start + 1;
    }

    /**
     * Get offset end.
     *
     * @return int
     */
    public function getOffsetEnd(): int
    {
        return $this->end;
    }
}
