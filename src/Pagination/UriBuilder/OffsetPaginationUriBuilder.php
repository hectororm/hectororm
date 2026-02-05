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

namespace Hector\Pagination\UriBuilder;

use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class OffsetPaginationUriBuilder implements PaginationUriBuilderInterface
{
    public function __construct(
        private string $pageParam = 'page',
        private ?string $perPageParam = 'per_page',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function buildUri(UriInterface $baseUri, PaginationRequestInterface $request): UriInterface
    {
        if (!$request instanceof OffsetPaginationRequest) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', OffsetPaginationRequest::class, $request::class)
            );
        }

        parse_str($baseUri->getQuery(), $query);
        $query[$this->pageParam] = $request->getPage();

        if (null !== $this->perPageParam) {
            $query[$this->perPageParam] = $request->getLimit();
        }

        return $baseUri->withQuery(http_build_query($query));
    }
}
