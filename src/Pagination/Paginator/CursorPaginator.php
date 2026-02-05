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

namespace Hector\Pagination\Paginator;

use Hector\Pagination\CursorPaginationInterface;
use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Encoder\CursorEncoderInterface;
use Hector\Pagination\Navigator\CursorPaginationNavigator;
use Hector\Pagination\Navigator\PaginationNavigatorInterface;
use Hector\Pagination\PaginationInterface;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\UriBuilder\CursorPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

final class CursorPaginator implements PaginatorInterface
{
    use LinkHeaderTrait;
    use PaginationViewTrait;

    private PaginationUriBuilderInterface $uriBuilder;
    private CursorEncoderInterface $encoder;

    public function __construct(
        private string $cursorParam = 'cursor',
        private string $perPageParam = 'per_page',
        private int $defaultPerPage = 15,
        private int|false $maxPerPage = false,
        ?CursorEncoderInterface $encoder = null,
        ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->encoder = $encoder ?? new Base64CursorEncoder();
        $this->uriBuilder = $uriBuilder ?? new CursorPaginationUriBuilder(
            cursorParam: $this->cursorParam,
            perPageParam: $this->maxPerPage !== false ? $this->perPageParam : null,
            encoder: $this->encoder,
        );
    }

    /**
     * Get cursor encoder.
     */
    public function getEncoder(): CursorEncoderInterface
    {
        return $this->encoder;
    }

    /**
     * @inheritDoc
     */
    public function createRequest(ServerRequestInterface $request): CursorPaginationRequest
    {
        return CursorPaginationRequest::fromRequest(
            $request,
            $this->cursorParam,
            $this->maxPerPage !== false ? $this->perPageParam : null,
            $this->defaultPerPage,
            $this->maxPerPage,
            $this->encoder,
        );
    }

    /**
     * @inheritDoc
     */
    public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface
    {
        if (!$pagination instanceof CursorPaginationInterface) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', CursorPaginationInterface::class, $pagination::class)
            );
        }

        return new CursorPaginationNavigator(
            pagination: $pagination,
            uriBuilder: $this->uriBuilder,
        );
    }
}
