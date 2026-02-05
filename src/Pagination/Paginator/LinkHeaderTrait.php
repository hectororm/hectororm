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
use Hector\Pagination\PaginationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

trait LinkHeaderTrait
{
    abstract public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface;

    public function prepareResponse(
        ResponseInterface $response,
        UriInterface $baseUri,
        PaginationInterface $pagination,
    ): ResponseInterface {
        return $this->addLinkHeader($response, $baseUri, $this->createNavigator($pagination));
    }

    protected function addLinkHeader(
        ResponseInterface $response,
        UriInterface $baseUri,
        PaginationNavigatorInterface $navigator,
    ): ResponseInterface {
        $links = [];

        if ($uri = $navigator->getFirstUri($baseUri)) {
            $links[] = sprintf('<%s>; rel="first"', $uri);
        }
        if ($uri = $navigator->getPreviousUri($baseUri)) {
            $links[] = sprintf('<%s>; rel="prev"', $uri);
        }
        if ($uri = $navigator->getNextUri($baseUri)) {
            $links[] = sprintf('<%s>; rel="next"', $uri);
        }
        if ($uri = $navigator->getLastUri($baseUri)) {
            $links[] = sprintf('<%s>; rel="last"', $uri);
        }

        if ([] !== $links) {
            $response = $response->withHeader('Link', implode(', ', $links));
        }

        return $response;
    }
}
