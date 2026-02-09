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

use Hector\Pagination\CursorPaginationInterface;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\UriBuilder\CursorPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;

final class CursorPaginationNavigator implements PaginationNavigatorInterface
{
    use UriBuilderTrait;

    public function __construct(
        private CursorPaginationInterface $pagination,
        private ?PaginationUriBuilderInterface $uriBuilder = null,
    ) {
        $this->uriBuilder ??= new CursorPaginationUriBuilder();
    }

    /**
     * @inheritDoc
     */
    public function getCurrentRequest(): ?PaginationRequestInterface
    {
        return null;
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
    public function getFirstRequest(): CursorPaginationRequest
    {
        return new CursorPaginationRequest(
            perPage: $this->pagination->getPerPage(),
            position: null,
        );
    }

    /**
     * @inheritDoc
     */
    public function getLastRequest(): ?PaginationRequestInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getPreviousRequest(): ?CursorPaginationRequest
    {
        if (!$this->pagination->hasPrevious()) {
            return null;
        }

        return new CursorPaginationRequest(
            perPage: $this->pagination->getPerPage(),
            position: $this->pagination->getPreviousPosition(),
            direction: CursorPaginationRequest::DIRECTION_BACKWARD,
        );
    }

    /**
     * @inheritDoc
     */
    public function getNextRequest(): ?CursorPaginationRequest
    {
        if (!$this->pagination->hasMore()) {
            return null;
        }

        return new CursorPaginationRequest(
            perPage: $this->pagination->getPerPage(),
            position: $this->pagination->getNextPosition(),
        );
    }
}
