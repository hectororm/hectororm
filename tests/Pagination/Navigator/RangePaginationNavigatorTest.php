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

namespace Hector\Pagination\Tests\Navigator;

use Berlioz\Http\Message\Uri;
use Hector\Pagination\Navigator\RangePaginationNavigator;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\RangePaginationRequest;
use PHPUnit\Framework\TestCase;

class RangePaginationNavigatorTest extends TestCase
{
    public function testGetFirstRequest(): void
    {
        $pagination = new RangePagination(['a', 'b'], 20, 39, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $request = $navigator->getFirstRequest();

        $this->assertInstanceOf(RangePaginationRequest::class, $request);
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
    }

    public function testGetLastRequest(): void
    {
        $pagination = new RangePagination(['a', 'b'], 0, 19, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $request = $navigator->getLastRequest();

        $this->assertInstanceOf(RangePaginationRequest::class, $request);
        $this->assertSame(80, $request->getOffset());
        $this->assertSame(99, $request->getOffsetEnd());
    }

    public function testGetLastRequestWithoutTotal(): void
    {
        $pagination = new RangePagination(['a', 'b'], 0, 19);
        $navigator = new RangePaginationNavigator($pagination);

        $this->assertNull($navigator->getLastRequest());
    }

    public function testGetPreviousRequest(): void
    {
        $pagination = new RangePagination(['a', 'b'], 20, 39, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $request = $navigator->getPreviousRequest();

        $this->assertInstanceOf(RangePaginationRequest::class, $request);
        $this->assertSame(0, $request->getOffset());
        $this->assertSame(19, $request->getOffsetEnd());
    }

    public function testGetPreviousRequestOnFirstPage(): void
    {
        $pagination = new RangePagination(['a', 'b'], 0, 19, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $this->assertNull($navigator->getPreviousRequest());
    }

    public function testGetNextRequest(): void
    {
        $pagination = new RangePagination(range(0, 19), 0, 19, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $request = $navigator->getNextRequest();

        $this->assertInstanceOf(RangePaginationRequest::class, $request);
        $this->assertSame(20, $request->getOffset());
        $this->assertSame(39, $request->getOffsetEnd());
    }

    public function testGetNextRequestOnLastPage(): void
    {
        $pagination = new RangePagination(['a', 'b'], 80, 99, total: 100);
        $navigator = new RangePaginationNavigator($pagination);

        $this->assertNull($navigator->getNextRequest());
    }

    public function testGetFirstUri(): void
    {
        $pagination = new RangePagination(['a', 'b'], 20, 39, total: 100);
        $navigator = new RangePaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getFirstUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('range=0-19', (string) $uri);
    }

    public function testGetNextUri(): void
    {
        $pagination = new RangePagination(range(0, 19), 0, 19, total: 100);
        $navigator = new RangePaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items?foo=bar');

        $uri = $navigator->getNextUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('range=20-39', (string) $uri);
        $this->assertStringContainsString('foo=bar', (string) $uri);
    }

    public function testGetLastUri(): void
    {
        $pagination = new RangePagination(['a', 'b'], 0, 19, total: 100);
        $navigator = new RangePaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getLastUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('range=80-99', (string) $uri);
    }
}
