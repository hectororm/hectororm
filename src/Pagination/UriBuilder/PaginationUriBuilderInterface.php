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

use Hector\Pagination\Request\PaginationRequestInterface;
use Psr\Http\Message\UriInterface;

interface PaginationUriBuilderInterface
{
    /**
     * Build URI with pagination query parameters.
     *
     * @param UriInterface $baseUri
     * @param PaginationRequestInterface $request
     *
     * @return UriInterface
     */
    public function buildUri(UriInterface $baseUri, PaginationRequestInterface $request): UriInterface;
}
