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

namespace Hector\Query\Tests\Pagination;

use Hector\Connection\Connection;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Query\Pagination\QueryRangePaginator;
use Hector\Query\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QueryRangePaginatorTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testPaginateReturnsRangePagination(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 1);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(RangePagination::class, $result);
        $this->assertEquals(0, $result->getStart());
        $this->assertEquals(1, $result->getEnd());
    }

    public function testPaginateWithTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryRangePaginator($builder, withTotal: true);
        $request = new RangePaginationRequest(start: 0, end: 0);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThanOrEqual(2, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 0);

        $result = $paginator->paginate($request);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateThrowsOnInvalidRequestType(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryRangePaginator($builder);
        $request = new OffsetPaginationRequest();

        $this->expectException(InvalidArgumentException::class);
        $paginator->paginate($request);
    }

    public function testPaginateWithBuilderOffset(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->offset(2);

        $paginator = new QueryRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 2);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        // Offset 2 skips the first 2 rows, range 0-2 gets 3 items starting at table_id=3
        $this->assertCount(3, $items);
        $this->assertEquals(3, $items[0]['table_id']);
    }

    public function testPaginateWithBuilderLimit(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->limit(5);

        $paginator = new QueryRangePaginator($builder, withTotal: true);
        $request = new RangePaginationRequest(start: 0, end: 2);

        $result = $paginator->paginate($request);

        // First 3 items within a window of 5
        $this->assertCount(3, $result->getItems());
        $this->assertEquals(5, $result->getTotal());
    }

    public function testPaginateWithBuilderLimitBoundsItems(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->limit(5);

        $paginator = new QueryRangePaginator($builder, withTotal: false);
        // Request range 3-9 but builder limit is 5, so only 2 items remain (index 3-4)
        $request = new RangePaginationRequest(start: 3, end: 9);

        $result = $paginator->paginate($request);

        $this->assertCount(2, $result->getItems());
    }

    public function testPaginateWithBuilderOffsetAndLimit(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->offset(2)
            ->limit(5);

        $paginator = new QueryRangePaginator($builder, withTotal: true);
        $request = new RangePaginationRequest(start: 0, end: 2);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        // Offset 2 + range start 0: SQL offset = 2, gets 3 items (table_id 3,4,5)
        $this->assertCount(3, $items);
        $this->assertEquals(3, $items[0]['table_id']);
        // Total bounded: min(10 - 2, 5) = 5
        $this->assertEquals(5, $result->getTotal());
    }
}
