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
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Pagination\UriBuilder\PaginationUriBuilderInterface;
use Hector\Pagination\UriBuilder\RangePaginationUriBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RangePaginationUriBuilderTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $builder = new RangePaginationUriBuilder();

        $this->assertInstanceOf(PaginationUriBuilderInterface::class, $builder);
    }

    public function testBuildUri(): void
    {
        $builder = new RangePaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new RangePaginationRequest(start: 20, end: 39);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('range=20-39', (string) $uri);
    }

    public function testBuildUriWithCustomParam(): void
    {
        $builder = new RangePaginationUriBuilder(rangeParam: 'items');
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new RangePaginationRequest(start: 0, end: 19);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('items=0-19', (string) $uri);
        $this->assertStringNotContainsString('range=', (string) $uri);
    }

    public function testBuildUriPreservesExistingQueryParams(): void
    {
        $builder = new RangePaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?filter=active&sort=name');
        $request = new RangePaginationRequest(start: 10, end: 29);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('filter=active', (string) $uri);
        $this->assertStringContainsString('sort=name', (string) $uri);
        $this->assertStringContainsString('range=10-29', (string) $uri);
    }

    public function testBuildUriOverwritesExistingRangeParam(): void
    {
        $builder = new RangePaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items?range=0-9');
        $request = new RangePaginationRequest(start: 50, end: 74);

        $uri = $builder->buildUri($baseUri, $request);

        $this->assertStringContainsString('range=50-74', (string) $uri);
        $this->assertStringNotContainsString('range=0-9', (string) $uri);
    }

    public function testBuildUriThrowsOnInvalidRequest(): void
    {
        $builder = new RangePaginationUriBuilder();
        $baseUri = Uri::create('https://gethectororm.com/api/items');
        $request = new OffsetPaginationRequest();

        $this->expectException(InvalidArgumentException::class);

        $builder->buildUri($baseUri, $request);
    }
}
