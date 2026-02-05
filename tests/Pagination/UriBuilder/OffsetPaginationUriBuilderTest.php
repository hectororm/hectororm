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
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\UriBuilder\OffsetPaginationUriBuilder;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OffsetPaginationUriBuilderTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $builder = new OffsetPaginationUriBuilder();

        $this->assertInstanceOf(PaginationUriBuilderInterface::class, $builder);
    }

    public function testBuildUri(): void
    {
        $builder = new OffsetPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new OffsetPaginationRequest(page: 3, perPage: 20);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('page=3', (string)$uri);
        $this->assertStringContainsString('per_page=20', (string)$uri);
    }

    public function testBuildUriWithCustomParams(): void
    {
        $builder = new OffsetPaginationUriBuilder(pageParam: 'p', perPageParam: 'limit');
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new OffsetPaginationRequest(page: 5, perPage: 50);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('p=5', (string)$uri);
        $this->assertStringContainsString('limit=50', (string)$uri);
        $this->assertStringNotContainsString('page=', (string)$uri);
        $this->assertStringNotContainsString('per_page=', (string)$uri);
    }

    public function testBuildUriWithoutPerPageParam(): void
    {
        $builder = new OffsetPaginationUriBuilder(pageParam: 'page', perPageParam: null);
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new OffsetPaginationRequest(page: 2, perPage: 15);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('page=2', (string)$uri);
        $this->assertStringNotContainsString('per_page', (string)$uri);
    }

    public function testBuildUriPreservesExistingQueryParams(): void
    {
        $builder = new OffsetPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?filter=active&sort=name');
        $request = new OffsetPaginationRequest(page: 2, perPage: 10);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('filter=active', (string)$uri);
        $this->assertStringContainsString('sort=name', (string)$uri);
        $this->assertStringContainsString('page=2', (string)$uri);
        $this->assertStringContainsString('per_page=10', (string)$uri);
    }

    public function testBuildUriOverwritesExistingPaginationParams(): void
    {
        $builder = new OffsetPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?page=1&per_page=5');
        $request = new OffsetPaginationRequest(page: 3, perPage: 20);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('page=3', (string)$uri);
        $this->assertStringContainsString('per_page=20', (string)$uri);
        $this->assertStringNotContainsString('page=1', (string)$uri);
        $this->assertStringNotContainsString('per_page=5', (string)$uri);
    }

    public function testBuildUriThrowsOnInvalidRequest(): void
    {
        $builder = new OffsetPaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new CursorPaginationRequest();

        $this->expectException(InvalidArgumentException::class);

        $builder->buildUri($baseUri, $request);
    }
}
