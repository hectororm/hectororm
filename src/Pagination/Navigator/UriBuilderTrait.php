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

use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use Psr\Http\Message\UriInterface;

trait UriBuilderTrait
{
    /**
     * Get URI builder.
     *
     * @return PaginationUriBuilderInterface
     */
    abstract protected function getUriBuilder(): PaginationUriBuilderInterface;

    /**
     * @see PaginationNavigatorInterface::getFirstRequest()
     */
    abstract public function getFirstRequest(): ?PaginationRequestInterface;

    /**
     * @see PaginationNavigatorInterface::getLastRequest()
     */
    abstract public function getLastRequest(): ?PaginationRequestInterface;

    /**
     * @see PaginationNavigatorInterface::getPreviousRequest()
     */
    abstract public function getPreviousRequest(): ?PaginationRequestInterface;

    /**
     * @see PaginationNavigatorInterface::getNextRequest()
     */
    abstract public function getNextRequest(): ?PaginationRequestInterface;

    /**
     * @see PaginationNavigatorInterface::getFirstUri()
     */
    public function getFirstUri(UriInterface $baseUri): ?UriInterface
    {
        if (null === ($request = $this->getFirstRequest())) {
            return null;
        }

        return $this->getUriBuilder()->buildUri($baseUri, $request);
    }

    /**
     * @see PaginationNavigatorInterface::getLastUri()
     */
    public function getLastUri(UriInterface $baseUri): ?UriInterface
    {
        if (null === ($request = $this->getLastRequest())) {
            return null;
        }

        return $this->getUriBuilder()->buildUri($baseUri, $request);
    }

    /**
     * @see PaginationNavigatorInterface::getPreviousUri()
     */
    public function getPreviousUri(UriInterface $baseUri): ?UriInterface
    {
        if (null === ($request = $this->getPreviousRequest())) {
            return null;
        }

        return $this->getUriBuilder()->buildUri($baseUri, $request);
    }

    /**
     * @see PaginationNavigatorInterface::getNextUri()
     */
    public function getNextUri(UriInterface $baseUri): ?UriInterface
    {
        if (null === ($request = $this->getNextRequest())) {
            return null;
        }

        return $this->getUriBuilder()->buildUri($baseUri, $request);
    }
}
