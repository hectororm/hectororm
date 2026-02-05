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
}
