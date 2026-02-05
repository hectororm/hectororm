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
            $secondPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1, position: $nextPosition));
            $this->assertInstanceOf(CursorPagination::class, $secondPage);
        }
    }
}
