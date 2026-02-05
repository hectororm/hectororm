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

namespace Hector\Pagination\Tests\UriBuilder;

use Berlioz\Http\Message\Uri;
use Hector\Pagination\Encoder\Base64CursorEncoder;
use Hector\Pagination\Encoder\SignedCursorEncoder;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\UriBuilder\CursorPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CursorPaginationUriBuilderTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $builder = new CursorPaginationUriBuilder();

        $this->assertInstanceOf(PaginationUriBuilderInterface::class, $builder);
    }

    public function testBuildUriWithPosition(): void
    {
        $builder = new CursorPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest(perPage: 20, position: ['id' => 42]);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('cursor=', (string) $uri);
        $this->assertStringContainsString('per_page=20', (string) $uri);

        // Verify cursor is properly encoded
        parse_str($uri->getQuery(), $query);
        $encoder = new Base64CursorEncoder();
        $this->assertSame(['id' => 42], $encoder->decode($query['cursor']));
    }

    public function testBuildUriWithoutPosition(): void
    {
        $builder = new CursorPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest(perPage: 15, position: null);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringNotContainsString('cursor=', (string) $uri);
        $this->assertStringContainsString('per_page=15', (string) $uri);
    }

    public function testBuildUriWithCustomParams(): void
    {
        $builder = new CursorPaginationUriBuilder(cursorParam: 'after', perPageParam: 'limit');
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest(perPage: 25, position: ['id' => 10]);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('after=', (string) $uri);
        $this->assertStringContainsString('limit=25', (string) $uri);
        $this->assertStringNotContainsString('cursor=', (string) $uri);
        $this->assertStringNotContainsString('per_page=', (string) $uri);
    }

    public function testBuildUriWithoutPerPageParam(): void
    {
        $builder = new CursorPaginationUriBuilder(cursorParam: 'cursor', perPageParam: null);
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest(perPage: 20, position: ['id' => 42]);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('cursor=', (string) $uri);
        $this->assertStringNotContainsString('per_page', (string) $uri);
    }

    public function testBuildUriWithCustomEncoder(): void
    {
        $encoder = new SignedCursorEncoder(
            inner: new Base64CursorEncoder(),
            secret: 'test-secret',
        );
        $builder = new CursorPaginationUriBuilder(encoder: $encoder);
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest(perPage: 10, position: ['id' => 42]);

        $uri = $builder->buildUri($baseUri, $request);

        parse_str($uri->getQuery(), $query);
        $this->assertStringContainsString('.', $query['cursor']); // Signed format contains dot
        $this->assertSame(['id' => 42], $encoder->decode($query['cursor']));
    }

    public function testBuildUriPreservesExistingQueryParams(): void
    {
        $builder = new CursorPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?filter=active&sort=name');
        $request = new CursorPaginationRequest(perPage: 10, position: ['id' => 5]);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('filter=active', (string) $uri);
        $this->assertStringContainsString('sort=name', (string) $uri);
        $this->assertStringContainsString('cursor=', (string) $uri);
        $this->assertStringContainsString('per_page=10', (string) $uri);
    }

    public function testBuildUriRemovesOldCursorWhenPositionNull(): void
    {
        $builder = new CursorPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?cursor=oldcursor&per_page=10');
        $request = new CursorPaginationRequest(perPage: 10, position: null);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringNotContainsString('cursor=', (string) $uri);
        $this->assertStringContainsString('per_page=10', (string) $uri);
    }

    public function testBuildUriThrowsOnInvalidRequest(): void
    {
        $builder = new CursorPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new OffsetPaginationRequest();

        $this->expectException(InvalidArgumentException::class);

        $builder->buildUri($baseUri, $request);
    }
}
