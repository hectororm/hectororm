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

use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class RangePaginationUriBuilder implements PaginationUriBuilderInterface
{
    public function __construct(
        private string $rangeParam = 'range',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function buildUri(UriInterface $baseUri, PaginationRequestInterface $request): UriInterface
    {
        if (!$request instanceof RangePaginationRequest) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', RangePaginationRequest::class, $request::class)
            );
        }

        parse_str($baseUri->getQuery(), $query);
        $query[$this->rangeParam] = sprintf(
            '%d-%d',
            $request->getOffset(),
            $request->getOffsetEnd(),
        );

        return $baseUri->withQuery(http_build_query($query));
    }
}
