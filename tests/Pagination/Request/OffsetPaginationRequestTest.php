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

namespace Hector\Pagination\Tests\Request;

use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class OffsetPaginationRequestTest extends TestCase
{
    public function testDefaults(): void
    {
        $request = new OffsetPaginationRequest();

        $this->assertSame(1, $request->getPage());
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(15, $request->getLimit());
    }

    public function testCustomValues(): void
    {
        $request = new OffsetPaginationRequest(page: 5, perPage: 25);

        $this->assertSame(5, $request->getPage());
        $this->assertSame(100, $request->getOffset());
        $this->assertSame(25, $request->getLimit());
    }

    public function testGetLimit(): void
    {
        $request = new OffsetPaginationRequest(perPage: 20);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(20, $request->getLimit());
    }

    public function testGetOffset(): void
    {
        $request = new OffsetPaginationRequest(page: 1, perPage: 20);
        $this->assertSame(0, $request->getOffset());

        $request = new OffsetPaginationRequest(page: 2, perPage: 20);
        $this->assertSame(20, $request->getOffset());

        $request = new OffsetPaginationRequest(page: 5, perPage: 15);
        $this->assertSame(60, $request->getOffset());
    }

    public function testFromRequest(): void
    {
        $serverRequest = $this->createServerRequest(['page' => '3', 'per_page' => '25']);

        $request = OffsetPaginationRequest::fromRequest($serverRequest, maxPerPage: 50);

        $this->assertSame(3, $request->getPage());
        $this->assertSame(50, $request->getOffset());
        $this->assertSame(25, $request->getLimit());
    }

    public function testFromRequestDefaults(): void
    {
        $serverRequest = $this->createServerRequest([]);

        $request = OffsetPaginationRequest::fromRequest($serverRequest);

        $this->assertSame(1, $request->getPage());
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(15, $request->getLimit());
    }

    public function testFromRequestCustomDefaults(): void
    {
        $serverRequest = $this->createServerRequest([]);

        $request = OffsetPaginationRequest::fromRequest(
            $serverRequest,
            defaultPerPage: 50,
        );

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(50, $request->getLimit());
    }

    public function testFromRequestCustomParams(): void
    {
        $serverRequest = $this->createServerRequest(['p' => '2', 'limit' => '30']);

        $request = OffsetPaginationRequest::fromRequest(
            $serverRequest,
            pageParam: 'p',
            perPageParam: 'limit',
            maxPerPage: 50,
        );

        $this->assertSame(2, $request->getPage());
        $this->assertSame(30, $request->getOffset());
        $this->assertSame(30, $request->getLimit());
    }

    public function testFromRequestMinPage(): void
    {
        $serverRequest = $this->createServerRequest(['page' => '0']);

        $request = OffsetPaginationRequest::fromRequest($serverRequest);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(1, $request->getPage());
    }

    public function testFromRequestNegativePage(): void
    {
        $serverRequest = $this->createServerRequest(['page' => '-5']);

        $request = OffsetPaginationRequest::fromRequest($serverRequest);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(1, $request->getPage());
    }

    public function testFromRequestMaxPerPage(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '500']);

        $request = OffsetPaginationRequest::fromRequest($serverRequest, maxPerPage: 100);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(100, $request->getLimit());
    }

    public function testFromRequestMinPerPage(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '0']);

        $request = OffsetPaginationRequest::fromRequest($serverRequest, maxPerPage: 50);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(1, $request->getLimit());
    }

    private function createServerRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
