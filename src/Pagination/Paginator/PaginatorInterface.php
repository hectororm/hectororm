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
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\View\PaginationView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

interface PaginatorInterface
{
    /**
     * Create request from PSR-7 server request.
     */
    public function createRequest(ServerRequestInterface $request): PaginationRequestInterface;

    /**
     * Create navigator for given pagination.
     */
    public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface;

    /**
     * Create view for template rendering.
     */
    public function createView(PaginationInterface $pagination, UriInterface $baseUri): PaginationView;

    /**
     * Prepare response with pagination headers.
     */
    public function prepareResponse(
        ResponseInterface $response,
        UriInterface $baseUri,
        PaginationInterface $pagination,
    ): ResponseInterface;
}
