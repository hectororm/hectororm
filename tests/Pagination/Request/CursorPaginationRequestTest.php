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

use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Encoder\SignedCursorEncoder;
use Hector\Pagination\Request\CursorPaginationRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class CursorPaginationRequestTest extends TestCase
{
    public function testDefaults(): void
    {
        $request = new CursorPaginationRequest();

        $this->assertSame(15, $request->getLimit());
        $this->assertNull($request->getPosition());
    }

    public function testCustomValues(): void
    {
        $position = ['id' => 42];
        $request = new CursorPaginationRequest(perPage: 25, position: $position);

        $this->assertSame(25, $request->getLimit());
        $this->assertSame($position, $request->getPosition());
    }

    public function testGetLimit(): void
    {
        $request = new CursorPaginationRequest(perPage: 20);

        $this->assertSame(20, $request->getLimit());
    }

    public function testGetOffsetAlwaysZero(): void
    {
        $request = new CursorPaginationRequest(perPage: 20, position: ['id' => 1]);

        $this->assertSame(0, $request->getOffset());
    }

    public function testGetPositionNull(): void
    {
        $request = new CursorPaginationRequest(position: null);

        $this->assertNull($request->getPosition());
    }

    public function testGetPosition(): void
    {
        $position = ['id' => 42, 'created_at' => '2024-01-01'];
        $request = new CursorPaginationRequest(position: $position);

        $this->assertSame($position, $request->getPosition());
    }

    public function testFromCursor(): void
    {
        $encoder = new Base64CursorEncoder();
        $position = ['id' => 42, 'created_at' => '2024-01-01'];
        $cursor = $encoder->encode($position);

        $request = CursorPaginationRequest::fromCursor($cursor);

        $this->assertSame($position, $request->getPosition());
        $this->assertSame(15, $request->getLimit());
    }

    public function testFromCursorWithCustomEncoder(): void
    {
        $encoder = new SignedCursorEncoder(new Base64CursorEncoder(), secret: 'secret');
        $position = ['id' => 42];
        $cursor = $encoder->encode($position);

        $request = CursorPaginationRequest::fromCursor($cursor, encoder: $encoder);

        $this->assertSame($position, $request->getPosition());
    }

    public function testFromCursorWithNullCursor(): void
    {
        $request = CursorPaginationRequest::fromCursor(null);

        $this->assertNull($request->getPosition());
    }

    public function testFromCursorWithEmptyCursor(): void
    {
        $request = CursorPaginationRequest::fromCursor('');

        $this->assertNull($request->getPosition());
    }

    public function testFromRequest(): void
    {
        $encoder = new Base64CursorEncoder();
        $position = ['id' => 42];
        $cursor = $encoder->encode($position);

        $serverRequest = $this->createServerRequest(['cursor' => $cursor, 'per_page' => '25']);

        $request = CursorPaginationRequest::fromRequest(
            request: $serverRequest,
            maxPerPage: 50,
        );

        $this->assertSame(25, $request->getLimit());
        $this->assertSame($position, $request->getPosition());
    }

    public function testFromRequestDefaults(): void
    {
        $serverRequest = $this->createServerRequest([]);

        $request = CursorPaginationRequest::fromRequest($serverRequest);

        $this->assertSame(15, $request->getLimit());
        $this->assertNull($request->getPosition());
    }

    public function testFromRequestEmptyCursor(): void
    {
        $serverRequest = $this->createServerRequest(['cursor' => '']);

        $request = CursorPaginationRequest::fromRequest($serverRequest);

        $this->assertNull($request->getPosition());
    }

    public function testFromRequestCustomParams(): void
    {
        $encoder = new Base64CursorEncoder();
        $position = ['id' => 99];
        $cursor = $encoder->encode($position);

        $serverRequest = $this->createServerRequest(['after' => $cursor, 'limit' => '30']);

        $request = CursorPaginationRequest::fromRequest(
            $serverRequest,
            cursorParam: 'after',
            perPageParam: 'limit',
            maxPerPage: 50,
        );

        $this->assertSame($position, $request->getPosition());
        $this->assertSame(30, $request->getLimit());
    }

    public function testFromRequestMaxPerPage(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '500']);

        $request = CursorPaginationRequest::fromRequest($serverRequest, maxPerPage: 100);

        $this->assertSame(100, $request->getLimit());
    }

    public function testFromRequestMinPerPage(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '-5']);

        $request = CursorPaginationRequest::fromRequest($serverRequest, maxPerPage: 100);

        $this->assertSame(1, $request->getLimit());
    }

    public function testFromRequestLockedPerPage(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '50']);

        $request = CursorPaginationRequest::fromRequest($serverRequest, maxPerPage: false);

        $this->assertSame(15, $request->getLimit());
    }

    public function testFromRequestNullPerPageParam(): void
    {
        $serverRequest = $this->createServerRequest(['per_page' => '50']);

        $request = CursorPaginationRequest::fromRequest(
            $serverRequest,
            perPageParam: null,
            defaultPerPage: 20,
        );

        $this->assertSame(20, $request->getLimit());
    }

    private function createServerRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
