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
use Hector\Pagination\LengthAwarePagination;
use Hector\Pagination\Navigator\OffsetPaginationNavigator;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Request\OffsetPaginationRequest;
use PHPUnit\Framework\TestCase;

class OffsetPaginationNavigatorTest extends TestCase
{
    public function testGetFirstRequest(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 3);
        $navigator = new OffsetPaginationNavigator($pagination);

        $request = $navigator->getFirstRequest();

        $this->assertInstanceOf(OffsetPaginationRequest::class, $request);
        $this->assertSame(1, $request->getPage());
        $this->assertSame(15, $request->getLimit());
    }

    public function testGetLastRequestWithoutLengthAware(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, currentPage: 1);
        $navigator = new OffsetPaginationNavigator($pagination);

        $this->assertNull($navigator->getLastRequest());
    }

    public function testGetPreviousRequest(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 3);
        $navigator = new OffsetPaginationNavigator($pagination);

        $request = $navigator->getPreviousRequest();

        $this->assertInstanceOf(OffsetPaginationRequest::class, $request);
        $this->assertSame(2, $request->getPage());
    }

    public function testGetPreviousRequestOnFirstPage(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 1);
        $navigator = new OffsetPaginationNavigator($pagination);

        $this->assertNull($navigator->getPreviousRequest());
    }

    public function testGetNextRequest(): void
    {
        $pagination = new OffsetPagination(range(1, 15), 15, currentPage: 1, hasMore: true);
        $navigator = new OffsetPaginationNavigator($pagination);

        $request = $navigator->getNextRequest();

        $this->assertInstanceOf(OffsetPaginationRequest::class, $request);
        $this->assertSame(2, $request->getPage());
    }

    public function testGetNextRequestOnLastPage(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 1, hasMore: false);
        $navigator = new OffsetPaginationNavigator($pagination);

        $this->assertNull($navigator->getNextRequest());
    }

    public function testGetFirstUri(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 3);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getFirstUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('page=1', (string)$uri);
        $this->assertStringContainsString('per_page=15', (string)$uri);
    }

    public function testGetNextUri(): void
    {
        $pagination = new OffsetPagination(range(1, 15), 15, currentPage: 2, hasMore: true);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items?foo=bar');

        $uri = $navigator->getNextUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('page=3', (string)$uri);
        $this->assertStringContainsString('foo=bar', (string)$uri);
    }

    public function testGetPreviousUri(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15, currentPage: 3);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getPreviousUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('page=2', (string)$uri);
    }

    public function testGetLastUri(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, total: 50, currentPage: 1);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getLastUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('page=5', (string)$uri);
    }

    public function testGetLastUriNull(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, currentPage: 1);
        $navigator = new OffsetPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $this->assertNull($navigator->getLastUri($baseUri));
    }
}
