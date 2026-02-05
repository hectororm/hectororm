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

namespace Hector\Query\Pagination;

use Hector\Pagination\PaginationInterface;
use Hector\Pagination\Request\PaginationRequestInterface;

/**
 * @template T
 */
interface QueryPaginatorInterface
{
    /**
     * Paginate query results.
     *
     * @param PaginationRequestInterface $request
     *
     * @return PaginationInterface<T>
     */
    public function paginate(PaginationRequestInterface $request): PaginationInterface;
}
