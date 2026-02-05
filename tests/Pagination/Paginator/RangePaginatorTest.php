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
use Hector\Pagination\Navigator\RangePaginationNavigator;
use Hector\Pagination\Paginator\RangePaginator;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\RangePaginationRequest;
use PHPUnit\Framework\TestCase;

class RangePaginatorTest extends TestCase
{
    public function testCreateRequestFromQueryString(): void
    {
        $paginator = new RangePaginator();
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?range=20-39'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertInstanceOf(RangePaginationRequest::class, $request);
        $this->assertSame(20, $request->getOffset());
        $this->assertSame(39, $request->getOffsetEnd());
    }

    public function testCreateRequestFromHeader(): void
    {
        $paginator = new RangePaginator(rangeUnit: 'items');
        $serverRequest = (new ServerRequest('GET', Uri::create('https://gethectororm.com/api')))
            ->withHeader('Range', 'items=10-29');

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(10, $request->getOffset());
        $this->assertSame(29, $request->getOffsetEnd());
    }

    public function testCreateRequestWithDefaults(): void
    {
        $paginator = new RangePaginator(defaultLimit: 25);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(24, $request->getOffsetEnd());
    }

    public function testCreateRequestWithLockedLimit(): void
    {
        $paginator = new RangePaginator(defaultLimit: 20, maxLimit: false);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?range=0-99'));

        $request = $paginator->createRequest($serverRequest);

        // User input ignored, uses defaultLimit
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
    }

    public function testCreateRequestWithMaxLimit(): void
    {
        $paginator = new RangePaginator(maxLimit: 50);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?range=0-99'));

        $request = $paginator->createRequest($serverRequest);

        // Capped to maxLimit
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(49, $request->getOffsetEnd());
    }

    public function testCreateRequestFromHeaderWithLockedLimit(): void
    {
        $paginator = new RangePaginator(rangeUnit: 'items', defaultLimit: 20, maxLimit: false);
        $serverRequest = (new ServerRequest('GET', Uri::create('https://gethectororm.com/api')))
            ->withHeader('Range', 'items=10-99');

        $request = $paginator->createRequest($serverRequest);

        // User input ignored, uses defaultLimit starting from 0
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
    }

    public function testCreateNavigator(): void
    {
        $paginator = new RangePaginator();
        $pagination = new RangePagination(['a', 'b'], 0, 19, total: 100);

        $navigator = $paginator->createNavigator($pagination);

        $this->assertInstanceOf(RangePaginationNavigator::class, $navigator);
    }

    public function testPrepareResponse(): void
    {
        $paginator = new RangePaginator();
        $pagination = new RangePagination(range(0, 19), 20, 39, total: 100);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $this->assertTrue($response->hasHeader('Link'));
        $this->assertTrue($response->hasHeader('Content-Range'));
        $this->assertTrue($response->hasHeader('Accept-Ranges'));
        $this->assertSame(206, $response->getStatusCode());

        $this->assertSame('items 20-39/100', $response->getHeaderLine('Content-Range'));
        $this->assertSame('items', $response->getHeaderLine('Accept-Ranges'));
    }

    public function testPrepareResponseFirstPage(): void
    {
        $paginator = new RangePaginator();
        $pagination = new RangePagination(range(0, 19), 0, 19, total: 100);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="next"', $linkHeader);
        $this->assertStringNotContainsString('rel="prev"', $linkHeader);
        $this->assertSame(206, $response->getStatusCode());
    }

    public function testPrepareResponseComplete(): void
    {
        $paginator = new RangePaginator();
        $pagination = new RangePagination(range(0, 9), 0, 9, total: 10);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        // Complete response = 200, not 206
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPrepareResponseCustomUnit(): void
    {
        $paginator = new RangePaginator(rangeUnit: 'users');
        $pagination = new RangePagination(['a', 'b'], 0, 19, total: 100);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/users');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $this->assertSame('users 0-19/100', $response->getHeaderLine('Content-Range'));
        $this->assertSame('users', $response->getHeaderLine('Accept-Ranges'));
    }

    public function testCreateView(): void
    {
        $paginator = new RangePaginator();
        $pagination = new RangePagination(
            items: range(1, 20),
            start: 20,
            end: 39,
            total: 100,
        );
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertSame(20, $view->getStart());
        $this->assertSame(39, $view->getEnd());
        $this->assertSame(20, $view->getCount());
        $this->assertSame(100, $view->getTotal());
        $this->assertTrue($view->hasPosition());
        $this->assertTrue($view->hasTotal());
    }
}
