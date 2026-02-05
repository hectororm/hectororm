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

use Hector\Pagination\Navigator\OffsetPaginationNavigator;
use Hector\Pagination\Navigator\PaginationNavigatorInterface;
use Hector\Pagination\OffsetPaginationInterface;
use Hector\Pagination\PaginationInterface;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\UriBuilder\OffsetPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

final class OffsetPaginator implements PaginatorInterface
{
    use LinkHeaderTrait;
    use PaginationViewTrait;

    private PaginationUriBuilderInterface $uriBuilder;

    public function __construct(
        private string $pageParam = 'page',
        private string $perPageParam = 'per_page',
        private int $defaultPerPage = 15,
        private int|false $maxPerPage = false,
        ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->uriBuilder = $uriBuilder ?? new OffsetPaginationUriBuilder(
            $this->pageParam,
            $this->maxPerPage !== false ? $this->perPageParam : null,
        );
    }

    /**
     * @inheritDoc
     */
    public function createRequest(ServerRequestInterface $request): OffsetPaginationRequest
    {
        return OffsetPaginationRequest::fromRequest(
            $request,
            $this->pageParam,
            $this->maxPerPage !== false ? $this->perPageParam : null,
            $this->defaultPerPage,
            $this->maxPerPage,
        );
    }

    /**
     * @inheritDoc
     */
    public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface
    {
        if (!$pagination instanceof OffsetPaginationInterface) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', OffsetPaginationInterface::class, $pagination::class)
            );
        }

        return new OffsetPaginationNavigator($pagination, $this->uriBuilder);
    }
}
