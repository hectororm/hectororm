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
use Hector\Pagination\View\PaginationView;
use Psr\Http\Message\UriInterface;

trait PaginationViewTrait
{
    /**
     * @see PaginationView::createFromNavigator()
     */
    abstract public function createNavigator(PaginationInterface $pagination): PaginationNavigatorInterface;

    /**
     * @inheritDoc
     */
    public function createView(
        PaginationInterface $pagination,
        UriInterface $baseUri,
    ): PaginationView {
        return PaginationView::createFromNavigator(
            $this->createNavigator($pagination),
            $pagination,
            $baseUri,
        );
    }
}
