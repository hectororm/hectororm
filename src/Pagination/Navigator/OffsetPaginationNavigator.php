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

namespace Hector\Pagination\Navigator;

use Hector\Pagination\OffsetPaginationInterface;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\UriBuilder\OffsetPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;

final class OffsetPaginationNavigator implements PaginationNavigatorInterface
{
    use UriBuilderTrait;

    public function __construct(
        private OffsetPaginationInterface $pagination,
        private ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->uriBuilder ??= new OffsetPaginationUriBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentRequest(): OffsetPaginationRequest
    {
        return new OffsetPaginationRequest(
            page: $this->pagination->getCurrentPage(),
            perPage: $this->pagination->getPerPage(),
        );
    }

    /**
     * @inheritDoc
     */
    protected function getUriBuilder(): PaginationUriBuilderInterface
    {
        return $this->uriBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getFirstRequest(): OffsetPaginationRequest
    {
        return new OffsetPaginationRequest(
            page: 1,
            perPage: $this->pagination->getPerPage(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getLastRequest(): ?OffsetPaginationRequest
    {
        $totalPages = $this->pagination->getTotalPages();

        // No last page when the total is unknown.
        if (null === $totalPages) {
            return null;
        }

        // No last page when the result set is empty (0 pages): otherwise this would build a
        // request for page 0 (consistent with RangePaginationNavigator).
        if ($totalPages < 1) {
            return null;
        }

        return new OffsetPaginationRequest(
            page: $totalPages,
            perPage: $this->pagination->getPerPage(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getPreviousRequest(): ?OffsetPaginationRequest
    {
        if (!$this->pagination->hasPrevious()) {
            return null;
        }

        return new OffsetPaginationRequest(
            page: $this->pagination->getCurrentPage() - 1,
            perPage: $this->pagination->getPerPage(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): ?OffsetPaginationRequest
    {
        if (!$this->pagination->hasMore()) {
            return null;
        }

        return new OffsetPaginationRequest(
            page: $this->pagination->getCurrentPage() + 1,
            perPage: $this->pagination->getPerPage(),
        );
    }
}
