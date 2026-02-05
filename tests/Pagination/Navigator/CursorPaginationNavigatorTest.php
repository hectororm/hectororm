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
use Hector\Pagination\CursorPagination;
use Hector\Pagination\Navigator\CursorPaginationNavigator;
use Hector\Pagination\Request\CursorPaginationRequest;
use PHPUnit\Framework\TestCase;

class CursorPaginationNavigatorTest extends TestCase
{
    public function testGetFirstRequest(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15);
        $navigator = new CursorPaginationNavigator($pagination);

        $request = $navigator->getFirstRequest();

        $this->assertInstanceOf(CursorPaginationRequest::class, $request);
        $this->assertNull($request->getPosition());
        $this->assertSame(15, $request->getLimit());
    }

    public function testGetLastRequest(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15);
        $navigator = new CursorPaginationNavigator($pagination);

        $this->assertNull($navigator->getLastRequest());
    }

    public function testGetPreviousRequest(): void
    {
        $pagination = new CursorPagination(
            ['a', 'b'],
            15,
            previousPosition: ['id' => 10],
        );
        $navigator = new CursorPaginationNavigator($pagination);

        $request = $navigator->getPreviousRequest();

        $this->assertInstanceOf(CursorPaginationRequest::class, $request);
        $this->assertSame(['id' => 10], $request->getPosition());
    }

    public function testGetPreviousRequestNull(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15);
        $navigator = new CursorPaginationNavigator($pagination);

        $this->assertNull($navigator->getPreviousRequest());
    }

    public function testGetNextRequest(): void
    {
        $pagination = new CursorPagination(
            ['a', 'b'],
            15,
            nextPosition: ['id' => 42],
        );
        $navigator = new CursorPaginationNavigator($pagination);

        $request = $navigator->getNextRequest();

        $this->assertInstanceOf(CursorPaginationRequest::class, $request);
        $this->assertSame(['id' => 42], $request->getPosition());
    }

    public function testGetNextRequestNull(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15);
        $navigator = new CursorPaginationNavigator($pagination);

        $this->assertNull($navigator->getNextRequest());
    }

    public function testGetFirstUri(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15, nextPosition: ['id' => 42]);
        $navigator = new CursorPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items?cursor=old');

        $uri = $navigator->getFirstUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('per_page=15', (string) $uri);
        $this->assertStringNotContainsString('cursor=', (string) $uri);
    }

    public function testGetNextUri(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15, nextPosition: ['id' => 42]);
        $navigator = new CursorPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $uri = $navigator->getNextUri($baseUri);

        $this->assertNotNull($uri);
        $this->assertStringContainsString('cursor=', (string) $uri);
        $this->assertStringContainsString('per_page=15', (string) $uri);
    }

    public function testGetLastUri(): void
    {
        $pagination = new CursorPagination(['a', 'b'], 15);
        $navigator = new CursorPaginationNavigator($pagination);
        $baseUri = Uri::create('https://gethectororm.com/api/items');

        $this->assertNull($navigator->getLastUri($baseUri));
    }
}
