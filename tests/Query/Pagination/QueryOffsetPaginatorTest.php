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
}
