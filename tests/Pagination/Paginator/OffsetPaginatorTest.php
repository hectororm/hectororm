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

namespace Hector\Pagination\Tests\Paginator;

use Berlioz\Http\Message\Response;
use Berlioz\Http\Message\ServerRequest;
use Berlioz\Http\Message\Uri;
use Hector\Pagination\LengthAwarePagination;
use Hector\Pagination\Navigator\OffsetPaginationNavigator;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Paginator\OffsetPaginator;
use Hector\Pagination\Request\OffsetPaginationRequest;
use PHPUnit\Framework\TestCase;

class OffsetPaginatorTest extends TestCase
{
    public function testCreateRequest(): void
    {
        $paginator = new OffsetPaginator();
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?page=3&per_page=25'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertInstanceOf(OffsetPaginationRequest::class, $request);
        $this->assertSame(3, $request->getPage());
        $this->assertSame(OffsetPaginationRequest::DEFAULT_PER_PAGE, $request->getLimit());
    }

    public function testCreateRequestWithDefaults(): void
    {
        $paginator = new OffsetPaginator(defaultPerPage: 20);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(1, $request->getPage());
        $this->assertSame(20, $request->getLimit());
    }

    public function testCreateRequestWithLockedPerPage(): void
    {
        // maxPerPage = false (default) means perPage is locked
        $paginator = new OffsetPaginator(defaultPerPage: 15);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=100'));

        $request = $paginator->createRequest($serverRequest);

        // User input ignored, uses defaultPerPage
        $this->assertSame(15, $request->getLimit());
    }

    public function testCreateRequestWithMaxPerPage(): void
    {
        $paginator = new OffsetPaginator(maxPerPage: 50);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=100'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(50, $request->getLimit());
    }

    public function testCreateRequestWithMaxPerPageAllowsUserInput(): void
    {
        $paginator = new OffsetPaginator(maxPerPage: 50);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=25'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(25, $request->getLimit());
    }

    public function testCreateRequestWithCustomParams(): void
    {
        $paginator = new OffsetPaginator(pageParam: 'p', perPageParam: 'limit', maxPerPage: 100);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?p=2&limit=30'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(2, $request->getPage());
        $this->assertSame(30, $request->getLimit());
    }

    public function testCreateNavigator(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 2);

        $navigator = $paginator->createNavigator($pagination);

        $this->assertInstanceOf(OffsetPaginationNavigator::class, $navigator);
    }

    public function testPrepareResponse(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, total: 50, currentPage: 2);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $this->assertTrue($response->hasHeader('Link'));

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="first"', $linkHeader);
        $this->assertStringContainsString('rel="prev"', $linkHeader);
        $this->assertStringContainsString('rel="next"', $linkHeader);
        $this->assertStringContainsString('rel="last"', $linkHeader);
    }

    public function testPrepareResponseFirstPage(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, hasMore: true);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="first"', $linkHeader);
        $this->assertStringNotContainsString('rel="prev"', $linkHeader);
        $this->assertStringContainsString('rel="next"', $linkHeader);
    }

    public function testPrepareResponseLastPage(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(['a', 'b'], 10, currentPage: 5, hasMore: false);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="prev"', $linkHeader);
        $this->assertStringNotContainsString('rel="next"', $linkHeader);
    }

    public function testPrepareResponsePerPageNotInUrlWhenLocked(): void
    {
        $paginator = new OffsetPaginator(); // maxPerPage = false
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, hasMore: true);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringNotContainsString('per_page=', $linkHeader);
    }

    public function testPrepareResponsePerPageInUrlWhenModifiable(): void
    {
        $paginator = new OffsetPaginator(maxPerPage: 50);
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, hasMore: true);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('per_page=', $linkHeader);
    }

    public function testCreateView(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 2, hasMore: true);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertSame(10, $view->getStart());
        $this->assertSame(19, $view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertNull($view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertFalse($view->hasTotal());
    }

    public function testCreateViewWithLengthAware(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, total: 50, currentPage: 2);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertSame(10, $view->getStart());
        $this->assertSame(19, $view->getEnd());
        $this->assertSame(10, $view->getCount());
        $this->assertSame(50, $view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertTrue($view->hasTotal());
    }

    public function testCreateViewUris(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, total: 50, currentPage: 2);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertNotNull($view->getFirstUri());
        $this->assertNotNull($view->getPreviousUri());
        $this->assertNotNull($view->getNextUri());
        $this->assertNotNull($view->getLastUri());

        $this->assertStringContainsString('page=1', (string)$view->getFirstUri());
        $this->assertStringContainsString('page=1', (string)$view->getPreviousUri());
        $this->assertStringContainsString('page=3', (string)$view->getNextUri());
        $this->assertStringContainsString('page=5', (string)$view->getLastUri());
    }

    public function testCreateViewFirstPage(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, hasMore: true);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertSame(0, $view->getStart());
        $this->assertSame(9, $view->getEnd());
        $this->assertNull($view->getPreviousUri());
        $this->assertNotNull($view->getNextUri());
    }

    public function testCreateViewLastPage(): void
    {
        $paginator = new OffsetPaginator();
        $pagination = new OffsetPagination(['a', 'b', 'c'], 10, currentPage: 5, hasMore: false);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertSame(40, $view->getStart());
        $this->assertSame(42, $view->getEnd());
        $this->assertSame(3, $view->getCount());
        $this->assertNotNull($view->getPreviousUri());
        $this->assertNull($view->getNextUri());
    }
}
