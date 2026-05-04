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
use Hector\Pagination\CursorPagination;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Query\Pagination\QueryCursorPaginator;
use Hector\Query\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QueryCursorPaginatorTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testPaginateReturnsCursorPagination(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC');

        $paginator = new QueryCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 1);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(CursorPagination::class, $result);
        $this->assertEquals(1, $result->getPerPage());
    }

    public function testPaginateThrowsWithoutOrderBy(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $paginator = new QueryCursorPaginator($builder);
        $request = new CursorPaginationRequest(perPage: 1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ORDER BY');
        $paginator->paginate($request);
    }

    public function testPaginateThrowsOnInvalidRequestType(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id');

        $paginator = new QueryCursorPaginator($builder);
        $request = new OffsetPaginationRequest();

        $this->expectException(InvalidArgumentException::class);
        $paginator->paginate($request);
    }

    public function testPaginateWithTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id');

        $paginator = new QueryCursorPaginator($builder, withTotal: true);
        $request = new CursorPaginationRequest(perPage: 1);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
    }

    public function testPaginateWithPosition(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC');

        $paginator = new QueryCursorPaginator($builder, withTotal: false);

        // First page
        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1));
        $items = $firstPage->getItems();

        $this->assertNotEmpty($items);

        // Simulate next page with position
        $nextPosition = $firstPage->getNextPosition();
        if (null !== $nextPosition) {
            $secondPage = $paginator->paginate(new CursorPaginationRequest(
                perPage: 1,
                position: $nextPosition,
            ));
            $this->assertInstanceOf(CursorPagination::class, $secondPage);
        }
    }

    public function testCursorValidWhenOrderMatches(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC');

        $paginator = new QueryCursorPaginator($builder, withTotal: false);

        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1));
        $firstItems = $firstPage->getItems();
        $this->assertNotEmpty($firstItems);

        $nextPosition = $firstPage->getNextPosition();
        $this->assertNotNull($nextPosition);

        $secondPage = $paginator->paginate(new CursorPaginationRequest(
            perPage: 1,
            position: $nextPosition,
        ));

        $secondItems = $secondPage->getItems();
        $this->assertNotEmpty($secondItems);
        $this->assertNotEquals($firstItems[0]['table_id'], $secondItems[0]['table_id']);
        $this->assertGreaterThan($firstItems[0]['table_id'], $secondItems[0]['table_id']);
    }

    public function testCursorInvalidatedWhenPositionMissingColumns(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC');

        $paginator = new QueryCursorPaginator($builder, withTotal: false);

        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1));
        $firstItems = $firstPage->getItems();

        $result = $paginator->paginate(new CursorPaginationRequest(
            perPage: 1,
            position: ['unknown_column' => 999],
        ));

        $resultItems = $result->getItems();
        $this->assertEquals($firstItems[0]['table_id'], $resultItems[0]['table_id']);
    }

    public function testCursorInvalidatedWhenPositionHasNonScalarValue(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC');

        $paginator = new QueryCursorPaginator($builder, withTotal: false);

        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1));
        $firstItems = $firstPage->getItems();

        $result = $paginator->paginate(new CursorPaginationRequest(
            perPage: 1,
            position: ['table_id' => ['malicious' => 'array']],
        ));

        $resultItems = $result->getItems();
        $this->assertEquals($firstItems[0]['table_id'], $resultItems[0]['table_id']);
    }

    public function testPaginateWithBuilderOffset(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC')
            ->offset(2);

        $paginator = new QueryCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 3);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        // Offset 2 skips the first 2 rows on the first page
        $this->assertCount(3, $items);
        $this->assertEquals(3, $items[0]['table_id']);
    }

    public function testPaginateWithBuilderOffsetNotAppliedOnSubsequentPages(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC')
            ->offset(2);

        $paginator = new QueryCursorPaginator($builder, withTotal: false);

        // First page: offset 2, gets items starting at table_id=3
        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 2));
        $firstItems = $firstPage->getItems();
        $this->assertEquals(3, $firstItems[0]['table_id']);

        // Second page: cursor navigates, builder offset should NOT be re-applied
        $nextPosition = $firstPage->getNextPosition();
        $this->assertNotNull($nextPosition);

        $secondPage = $paginator->paginate(new CursorPaginationRequest(
            perPage: 2,
            position: $nextPosition,
        ));
        $secondItems = $secondPage->getItems();
        $this->assertNotEmpty($secondItems);
        // Should continue right after first page, not skip 2 more rows
        $this->assertEquals($firstItems[1]['table_id'] + 1, $secondItems[0]['table_id']);
    }

    public function testPaginateWithBuilderLimitBoundsTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC')
            ->limit(5);

        $paginator = new QueryCursorPaginator($builder, withTotal: true);
        $request = new CursorPaginationRequest(perPage: 3);

        $result = $paginator->paginate($request);

        // Total should be bounded to 5, not the full table count
        $this->assertEquals(5, $result->getTotal());
    }

    public function testPaginateWithBuilderOffsetAndLimitBoundsTotal(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('table_id', 'ASC')
            ->offset(2)
            ->limit(5);

        $paginator = new QueryCursorPaginator($builder, withTotal: true);
        $request = new CursorPaginationRequest(perPage: 3);

        $result = $paginator->paginate($request);

        // Total: min(10 - 2, 5) = 5
        $this->assertEquals(5, $result->getTotal());
    }
}
