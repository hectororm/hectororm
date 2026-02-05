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

use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class RangePaginationRequestTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $request = new RangePaginationRequest();

        $this->assertInstanceOf(PaginationRequestInterface::class, $request);
    }

    public function testDefaults(): void
    {
        $request = new RangePaginationRequest();

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
        $this->assertSame(20, $request->getLimit());
    }

    public function testCustomValues(): void
    {
        $request = new RangePaginationRequest(start: 20, end: 39);

        $this->assertSame(20, $request->getOffset());
        $this->assertSame(39, $request->getOffsetEnd());
        $this->assertSame(20, $request->getLimit());
    }

    public function testGetLimit(): void
    {
        $request = new RangePaginationRequest(start: 0, end: 19);
        $this->assertSame(20, $request->getLimit());

        $request = new RangePaginationRequest(start: 20, end: 49);
        $this->assertSame(30, $request->getLimit());

        $request = new RangePaginationRequest(start: 0, end: 0);
        $this->assertSame(1, $request->getLimit());
    }

    public function testGetOffset(): void
    {
        $request = new RangePaginationRequest(start: 0, end: 19);
        $this->assertSame(0, $request->getOffset());

        $request = new RangePaginationRequest(start: 20, end: 39);
        $this->assertSame(20, $request->getOffset());
    }


    public function testGetOffsetEnd(): void
    {
        $request = new RangePaginationRequest(start: 0, end: 19);
        $this->assertSame(19, $request->getOffsetEnd());

        $request = new RangePaginationRequest(start: 20, end: 39);
        $this->assertSame(39, $request->getOffsetEnd());
    }

    public function testFromRequestRangeFormat(): void
    {
        $serverRequest = $this->createServerRequest(['range' => '20-39']);

        $request = RangePaginationRequest::fromRequest($serverRequest);

        $this->assertSame(20, $request->getOffset());
        $this->assertSame(39, $request->getOffsetEnd());
        $this->assertSame(20, $request->getLimit());
    }

    public function testFromRequestOffsetLimitFormat(): void
    {
        $serverRequest = $this->createServerRequest(['offset' => '20', 'limit' => '15']);

        $request = RangePaginationRequest::fromRequest($serverRequest);

        $this->assertSame(20, $request->getOffset());
        $this->assertSame(34, $request->getOffsetEnd());
        $this->assertSame(15, $request->getLimit());
    }

    public function testFromRequestDefaults(): void
    {
        $serverRequest = $this->createServerRequest([]);

        $request = RangePaginationRequest::fromRequest($serverRequest);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
        $this->assertSame(20, $request->getLimit());
    }

    public function testFromRequestCustomDefaultLimit(): void
    {
        $serverRequest = $this->createServerRequest([]);

        $request = RangePaginationRequest::fromRequest($serverRequest, defaultLimit: 50);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(49, $request->getOffsetEnd());
        $this->assertSame(50, $request->getLimit());
    }

    public function testFromRequestCustomParams(): void
    {
        $serverRequest = $this->createServerRequest(['from' => '10', 'size' => '25']);

        $request = RangePaginationRequest::fromRequest(
            $serverRequest,
            offsetParam: 'from',
            limitParam: 'size',
        );

        $this->assertSame(10, $request->getOffset());
        $this->assertSame(34, $request->getOffsetEnd());
        $this->assertSame(25, $request->getLimit());
    }

    public function testFromRequestMaxLimitRange(): void
    {
        $serverRequest = $this->createServerRequest(['range' => '0-500']);

        $request = RangePaginationRequest::fromRequest($serverRequest, maxLimit: 100);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(99, $request->getOffsetEnd());
        $this->assertSame(100, $request->getLimit());
    }

    public function testFromRequestMaxLimitOffsetFormat(): void
    {
        $serverRequest = $this->createServerRequest(['offset' => '0', 'limit' => '500']);

        $request = RangePaginationRequest::fromRequest($serverRequest, maxLimit: 100);

        $this->assertSame(0, $request->getOffset());
        $this->assertSame(99, $request->getOffsetEnd());
        $this->assertSame(100, $request->getLimit());
    }

    public function testFromRequestRangeTakesPrecedence(): void
    {
        $serverRequest = $this->createServerRequest([
            'range' => '10-29',
            'offset' => '0',
            'limit' => '50',
        ]);

        $request = RangePaginationRequest::fromRequest($serverRequest);

        $this->assertSame(10, $request->getOffset());
        $this->assertSame(29, $request->getOffsetEnd());
        $this->assertSame(20, $request->getLimit());
    }

    private function createServerRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
