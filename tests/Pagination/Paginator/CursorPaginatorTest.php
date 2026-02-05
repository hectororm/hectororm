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
use Hector\Pagination\CursorPagination;
use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Navigator\CursorPaginationNavigator;
use Hector\Pagination\Paginator\CursorPaginator;
use Hector\Pagination\Request\CursorPaginationRequest;
use PHPUnit\Framework\TestCase;

class CursorPaginatorTest extends TestCase
{
    public function testCreateRequest(): void
    {
        $paginator = new CursorPaginator();
        $encoder = new Base64CursorEncoder();
        $cursor = $encoder->encode(['id' => 42]);

        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?cursor=' . $cursor . '&per_page=25'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertInstanceOf(CursorPaginationRequest::class, $request);
        $this->assertSame($cursor, $request->getPosition() ? $encoder->encode($request->getPosition()) : null);
        $this->assertSame(CursorPaginationRequest::DEFAULT_PER_PAGE, $request->getLimit());
    }

    public function testCreateRequestWithoutCursor(): void
    {
        $paginator = new CursorPaginator(defaultPerPage: 20);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertNull($request->getPosition());
        $this->assertSame(20, $request->getLimit());
    }

    public function testCreateRequestWithLockedPerPage(): void
    {
        // maxPerPage = false (default) means perPage is locked
        $paginator = new CursorPaginator(defaultPerPage: 15);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=100'));

        $request = $paginator->createRequest($serverRequest);

        // User input ignored, uses defaultPerPage
        $this->assertSame(15, $request->getLimit());
    }

    public function testCreateRequestWithMaxPerPage(): void
    {
        $paginator = new CursorPaginator(maxPerPage: 50);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=100'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(50, $request->getLimit());
    }

    public function testCreateRequestWithMaxPerPageAllowsUserInput(): void
    {
        $paginator = new CursorPaginator(maxPerPage: 50);
        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?per_page=25'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(25, $request->getLimit());
    }

    public function testCreateRequestWithCustomParams(): void
    {
        $paginator = new CursorPaginator(cursorParam: 'after', perPageParam: 'limit', maxPerPage: 100);
        $encoder = $paginator->getEncoder();
        $cursor = $encoder->encode(['id' => 123]);

        $serverRequest = new ServerRequest('GET', Uri::create('https://gethectororm.com/api?after=' . $cursor . '&limit=30'));

        $request = $paginator->createRequest($serverRequest);

        $this->assertSame(['id' => 123], $request->getPosition());
        $this->assertSame(30, $request->getLimit());
    }

    public function testGetEncoder(): void
    {
        $paginator = new CursorPaginator();

        $this->assertInstanceOf(Base64CursorEncoder::class, $paginator->getEncoder());
    }

    public function testCreateNavigator(): void
    {
        $paginator = new CursorPaginator();
        $pagination = new CursorPagination(['a', 'b'], 15);

        $navigator = $paginator->createNavigator($pagination);

        $this->assertInstanceOf(CursorPaginationNavigator::class, $navigator);
    }

    public function testPrepareResponse(): void
    {
        $paginator = new CursorPaginator();
        $pagination = new CursorPagination(
            ['a', 'b'],
            15,
            nextPosition: ['id' => 42],
            previousPosition: ['id' => 10],
        );
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $this->assertTrue($response->hasHeader('Link'));

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="first"', $linkHeader);
        $this->assertStringContainsString('rel="prev"', $linkHeader);
        $this->assertStringContainsString('rel="next"', $linkHeader);
        $this->assertStringNotContainsString('rel="last"', $linkHeader);
    }

    public function testPrepareResponseFirstPage(): void
    {
        $paginator = new CursorPaginator();
        $pagination = new CursorPagination(['a', 'b'], 15, nextPosition: ['id' => 42]);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="next"', $linkHeader);
        $this->assertStringNotContainsString('rel="prev"', $linkHeader);
    }

    public function testPrepareResponsePerPageNotInUrlWhenLocked(): void
    {
        $paginator = new CursorPaginator(); // maxPerPage = false
        $pagination = new CursorPagination(['a', 'b'], 15, nextPosition: ['id' => 42]);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringNotContainsString('per_page=', $linkHeader);
    }

    public function testPrepareResponsePerPageInUrlWhenModifiable(): void
    {
        $paginator = new CursorPaginator(maxPerPage: 50);
        $pagination = new CursorPagination(['a', 'b'], 15, nextPosition: ['id' => 42]);
        $response = new Response();
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $response = $paginator->prepareResponse($response, $baseUri, $pagination);

        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('per_page=', $linkHeader);
    }

    public function testCreateViewReturnsNullPosition(): void
    {
        $paginator = new CursorPaginator();
        $pagination = new CursorPagination(
            items: range(1, 10),
            perPage: 10,
            nextPosition: ['next_abc'],
            previousPosition: ['prev_xyz'],
        );
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $view = $paginator->createView($pagination, $baseUri);

        $this->assertNull($view->getStart());
        $this->assertNull($view->getEnd());
        $this->assertFalse($view->hasPosition());
        $this->assertSame(10, $view->getCount());
    }
}
