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

namespace Hector\Pagination\Tests\View;

use Berlioz\Http\Message\Uri;
use Hector\Pagination\Navigator\CursorPaginationNavigator;
use Hector\Pagination\Navigator\OffsetPaginationNavigator;
use Hector\Pagination\Navigator\RangePaginationNavigator;
use Hector\Pagination\CursorPagination;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\RangePagination;
use Hector\Pagination\View\PaginationView;
use PHPUnit\Framework\TestCase;

class PaginationViewTest extends TestCase
{
    public function testConstructor(): void
    {
        $uri = Uri::create('https://gethectororm.com/page=1');

        $view = new PaginationView(
            start: 10,
            end: 19,
            count: 10,
            total: 100,
            firstUri: $uri,
            previousUri: $uri,
            nextUri: $uri,
            lastUri: $uri,
        );

        $this->assertSame(10, $view->getStart());
        $this->assertSame(19, $view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertSame(100, $view->getTotal());
    }

    public function testHasPosition(): void
    {
        $viewWithPosition = new PaginationView(
            start: 0,
            end: 9,
            count: 10,
            total: null,
            firstUri: null,
            previousUri: null,
            nextUri: null,
            lastUri: null,
        );

        $viewWithoutPosition = new PaginationView(
            start: null,
            end: null,
            count: 10,
            total: null,
            firstUri: null,
            previousUri: null,
            nextUri: null,
            lastUri: null,
        );

        $this->assertTrue($viewWithPosition->hasPosition());
        $this->assertFalse($viewWithoutPosition->hasPosition());
    }

    public function testHasTotal(): void
    {
        $viewWithTotal = new PaginationView(
            start: 0,
            end: 9,
            count: 10,
            total: 100,
            firstUri: null,
            previousUri: null,
            nextUri: null,
            lastUri: null,
        );

        $viewWithoutTotal = new PaginationView(
            start: 0,
            end: 9,
            count: 10,
            total: null,
            firstUri: null,
            previousUri: null,
            nextUri: null,
            lastUri: null,
        );

        $this->assertTrue($viewWithTotal->hasTotal());
        $this->assertFalse($viewWithoutTotal->hasTotal());
    }

    public function testCreateFromNavigatorWithOffsetPagination(): void
    {
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 2, hasMore: true);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(10, $view->getStart());
        $this->assertSame(19, $view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertNull($view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertFalse($view->hasTotal());
        $this->assertNotNull($view->getFirstUri());
        $this->assertNotNull($view->getPreviousUri());
        $this->assertNotNull($view->getNextUri());
    }

    public function testCreateFromNavigatorWithLengthAwarePagination(): void
    {
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 2, total: 50);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(10, $view->getStart());
        $this->assertSame(19, $view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertSame(50, $view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertTrue($view->hasTotal());
        $this->assertNotNull($view->getLastUri());
    }

    public function testCreateFromNavigatorWithRangePagination(): void
    {
        $pagination = new RangePagination(range(1, 20), start: 20, end: 39, total: 100);
        $navigator = new RangePaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(20, $view->getStart());
        $this->assertSame(39, $view->getEnd());
        $this->assertSame(20, $view->getCount());
        $this->assertSame(100, $view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertTrue($view->hasTotal());
    }

    public function testCreateFromNavigatorWithCursorPagination(): void
    {
        $pagination = new CursorPagination(
            items: range(1, 10),
            perPage: 10,
            nextPosition: ['id' => 42],
            previousPosition: ['id' => 10],
        );
        $navigator = new CursorPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertNull($view->getStart());
        $this->assertNull($view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertNull($view->getTotal());
        $this->assertFalse($view->hasPosition());
        $this->assertFalse($view->hasTotal());
        $this->assertNotNull($view->getNextUri());
        $this->assertNotNull($view->getPreviousUri());
    }

    public function testCreateFromNavigatorFirstPage(): void
    {
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, hasMore: true);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(0, $view->getStart());
        $this->assertSame(9, $view->getEnd());
        $this->assertNull($view->getPreviousUri());
        $this->assertNotNull($view->getNextUri());
    }

    public function testCreateFromNavigatorLastPage(): void
    {
        $pagination = new OffsetPagination(['a', 'b', 'c'], 10, currentPage: 5, hasMore: false);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(40, $view->getStart());
        $this->assertSame(42, $view->getEnd());
        $this->assertSame(3, $view->getCount());
        $this->assertNotNull($view->getPreviousUri());
        $this->assertNull($view->getNextUri());
    }

    public function testCreateFromNavigatorEmptyPagination(): void
    {
        $pagination = new OffsetPagination([], 10, currentPage: 1, hasMore: false);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = PaginationView::createFromNavigator($navigator, $pagination, $baseUri);

        $this->assertSame(0, $view->getStart());
        $this->assertSame(-1, $view->getEnd()); // 0 + 0 - 1
        $this->assertSame(0, $view->getCount());
    }
}
