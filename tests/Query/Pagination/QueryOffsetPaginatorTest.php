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
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Query\Pagination\QueryOffsetPaginator;
use Hector\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryOffsetPaginatorTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testPaginateReturnsOffsetPagination(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 2);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertLessThanOrEqual(2, count($result->getItems()));
        $this->assertEquals(2, $result->getPerPage());
        $this->assertEquals(1, $result->getCurrentPage());
    }

    public function testPaginateWithTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryOffsetPaginator($builder, withTotal: true);
        $request = new OffsetPaginationRequest(page: 1, perPage: 1);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThanOrEqual(2, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 1);

        $result = $paginator->paginate($request);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateHasMoreDetection(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 1);

        $result = $paginator->paginate($request);

        // Database has at least 2 rows, so hasMore should be true
        $this->assertTrue($result->hasMore());
    }

    public function testPaginateWithBuilderOffset(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->offset(2);

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 3);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        // Offset 2 skips the first 2 rows, so the first item should be table_id=3
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

        $paginator = new QueryOffsetPaginator($builder, withTotal: true);
        $request = new OffsetPaginationRequest(page: 1, perPage: 3);

        $result = $paginator->paginate($request);

        // First page of 3 items within a window of 5
        $this->assertCount(3, $result->getItems());
        $this->assertTrue($result->hasMore());
        $this->assertEquals(5, $result->getTotal());
    }

    public function testPaginateWithBuilderLimitHasMoreFalseAtBound(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->limit(5);

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 2, perPage: 3);

        $result = $paginator->paginate($request);

        // Page 2 of 3 within a window of 5: only 2 items remain (index 3-4)
        $this->assertCount(2, $result->getItems());
        $this->assertFalse($result->hasMore());
    }

    public function testPaginateWithBuilderOffsetAndLimit(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->offset(2)
            ->limit(5);

        $paginator = new QueryOffsetPaginator($builder, withTotal: true);
        $request = new OffsetPaginationRequest(page: 1, perPage: 3);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        // Offset 2 + page 1: starts at row 3, gets 3 items (3,4,5)
        $this->assertCount(3, $items);
        $this->assertEquals(3, $items[0]['table_id']);
        $this->assertTrue($result->hasMore());
        // Total bounded: min(10 - 2, 5) = 5
        $this->assertEquals(5, $result->getTotal());
    }

    public function testPaginateWithBuilderBoundsDoesNotMutateOriginal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id')
            ->offset(2)
            ->limit(5);

        $paginator = new QueryOffsetPaginator($builder, withTotal: false);
        $paginator->paginate(new OffsetPaginationRequest(page: 1, perPage: 3));

        // Original builder should still have its offset/limit intact
        $this->assertEquals(2, $builder->limit->getOffset());
        $this->assertEquals(5, $builder->limit->getLimit());
    }
}
