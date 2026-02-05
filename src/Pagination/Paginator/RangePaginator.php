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

use Hector\Pagination\Navigator\PaginationNavigatorInterface;
use Hector\Pagination\Navigator\RangePaginationNavigator;
use Hector\Pagination\PaginationInterface;
use Hector\Pagination\RangePaginationInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use Hector\Pagination\UriBuilder\RangePaginationUriBuilder;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

final class RangePaginator implements PaginatorInterface
{
    use LinkHeaderTrait;
    use PaginationViewTrait;

    private PaginationUriBuilderInterface $uriBuilder;

    public function __construct(
        private string $rangeParam = 'range',
        private string $rangeUnit = 'items',
        private int $defaultLimit = 20,
        private int|false $maxLimit = 100,
        ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->uriBuilder = $uriBuilder ?? new RangePaginationUriBuilder($this->rangeParam);
    }

    /**
     * @inheritDoc
     */
    public function createRequest(ServerRequestInterface $request): RangePaginationRequest
    {
        // If limit is locked, ignore user input
        if (false === $this->maxLimit) {
            return new RangePaginationRequest(0, $this->defaultLimit - 1);
        }

        $header = $request->getHeaderLine('Range');

        if ('' !== $header) {
            return RangePaginationRequest::fromHeader(
                $request,
                $this->rangeUnit,
                $this->defaultLimit - 1,
                $this->maxLimit,
            );
        }

        return RangePaginationRequest::fromRequest(
            $request,
            rangeParam: $this->rangeParam,
            defaultLimit: $this->defaultLimit,
            maxLimit: $this->maxLimit,
        );
    }

    /**
     * @inheritDoc
     */
    public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface
    {
        if (!$pagination instanceof RangePaginationInterface) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', RangePaginationInterface::class, $pagination::class)
            );
        }

        return new RangePaginationNavigator($pagination, $this->uriBuilder);
    }

    /**
     * @inheritDoc
     */
    public function prepareResponse(
        ResponseInterface $response,
        UriInterface $baseUri,
        PaginationInterface $pagination,
    ): ResponseInterface {
        if (!$pagination instanceof RangePaginationInterface) {
            throw new InvalidArgumentException(
                sprintf('Expected %s, got %s', RangePaginationInterface::class, $pagination::class)
            );
        }

        $navigator = $this->createNavigator($pagination);
        $response = $this->addLinkHeader($response, $baseUri, $navigator);

        $response = $response
            ->withHeader('Content-Range', $pagination->getContentRange($this->rangeUnit))
            ->withHeader('Accept-Ranges', $this->rangeUnit);

        if ($pagination->hasMore() || $pagination->hasPrevious()) {
            $response = $response->withStatus(206);
        }

        return $response;
    }
}
