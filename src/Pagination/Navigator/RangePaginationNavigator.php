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

use Hector\Pagination\RangePaginationInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use Hector\Pagination\UriBuilder\RangePaginationUriBuilder;

final class RangePaginationNavigator implements PaginationNavigatorInterface
{
    use UriBuilderTrait;

    public function __construct(
        private RangePaginationInterface $pagination,
        private ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->uriBuilder ??= new RangePaginationUriBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentRequest(): RangePaginationRequest
    {
        return new RangePaginationRequest(
            start: $this->pagination->getStart(),
            end: $this->pagination->getEnd(),
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
    public function getFirstRequest(): RangePaginationRequest
    {
        $size = $this->pagination->getPerPage();

        return new RangePaginationRequest(
            start: 0,
            end: $size - 1,
        );
    }

    /**
     * @inheritDoc
     */
    public function getLastRequest(): ?RangePaginationRequest
    {
        if (null === ($total = $this->pagination->getTotal())) {
            return null;
        }

        $size = $this->pagination->getPerPage();
        $start = (int)(floor(($total - 1) / $size) * $size);

        return new RangePaginationRequest(
            start: $start,
            end: $total - 1,
        );
    }

    /**
     * @inheritDoc
     */
    public function getPreviousRequest(): ?RangePaginationRequest
    {
        if (!$this->pagination->hasPrevious()) {
            return null;
        }

        $size = $this->pagination->getPerPage();
        $start = max(0, $this->pagination->getStart() - $size);

        return new RangePaginationRequest(
            start: $start,
            end: $start + $size - 1,
        );
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): ?RangePaginationRequest
    {
        if (!$this->pagination->hasMore()) {
            return null;
        }

        $size = $this->pagination->getPerPage();
        $start = $this->pagination->getEnd() + 1;
        $end = $start + $size - 1;

        if (null !== ($total = $this->pagination->getTotal())) {
            $end = min($end, $total - 1);
        }

        return new RangePaginationRequest(
            start: $start,
            end: $end,
        );
    }
}
