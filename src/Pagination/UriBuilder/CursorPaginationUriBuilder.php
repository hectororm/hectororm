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

use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Encoder\CursorEncoderInterface;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class CursorPaginationUriBuilder implements PaginationUriBuilderInterface
{
    private CursorEncoderInterface $encoder;

    public function __construct(
        private string $cursorParam = 'cursor',
        private ?string $perPageParam = 'per_page',
        ?CursorEncoderInterface $encoder = null,
    ) {
        $this->encoder = $encoder ?? new Base64CursorEncoder();
    }

    /**
     * @inheritDoc
     */
    public function buildUri(UriInterface $baseUri, PaginationRequestInterface $request): UriInterface
    {
        if (!$request instanceof CursorPaginationRequest) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', CursorPaginationRequest::class, $request::class)
            );
        }

        parse_str($baseUri->getQuery(), $query);

        if (null !== $this->perPageParam) {
            $query[$this->perPageParam] = $request->getLimit();
        }

        $position = $request->getPosition();
        if (null !== $position) {
            $query[$this->cursorParam] = $this->encoder->encode($position);
        } else {
            unset($query[$this->cursorParam]);
        }

        return $baseUri->withQuery(http_build_query($query));
    }
}
