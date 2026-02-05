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
use Hector\Pagination\PaginationInterface;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Query\Pagination\AbstractQueryPaginator;
use Hector\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class AbstractQueryPaginatorTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    private function createConcretePaginator(QueryBuilder $builder): AbstractQueryPaginator
    {
        return new class($builder) extends AbstractQueryPaginator {
            public function paginate(PaginationRequestInterface $request): PaginationInterface
            {
                return new OffsetPagination([], 10, 1, false);
            }

            // Expose protected methods for testing
            public function exposeCalculateCurrentPage(int $offset, int $limit): int
            {
                return $this->calculateCurrentPage($offset, $limit);
            }

            public function exposeNormalizeColumnKey(string $column): string
            {
                return $this->normalizeColumnKey($column);
            }
        };
    }

    public function testCalculateCurrentPage(): void
    {
        $builder = new QueryBuilder($this->getConnection());
        $paginator = $this->createConcretePaginator($builder);

        $this->assertEquals(1, $paginator->exposeCalculateCurrentPage(0, 10));
        $this->assertEquals(2, $paginator->exposeCalculateCurrentPage(10, 10));
        $this->assertEquals(3, $paginator->exposeCalculateCurrentPage(20, 10));
        $this->assertEquals(1, $paginator->exposeCalculateCurrentPage(5, 10));
    }

    public function testCalculateCurrentPageWithZeroLimit(): void
    {
        $builder = new QueryBuilder($this->getConnection());
        $paginator = $this->createConcretePaginator($builder);

        $this->assertEquals(1, $paginator->exposeCalculateCurrentPage(0, 0));
        $this->assertEquals(1, $paginator->exposeCalculateCurrentPage(100, 0));
    }

    public function testNormalizeColumnKey(): void
    {
        $builder = new QueryBuilder($this->getConnection());
        $paginator = $this->createConcretePaginator($builder);

        $this->assertEquals('column', $paginator->exposeNormalizeColumnKey('column'));
        $this->assertEquals('column', $paginator->exposeNormalizeColumnKey('`column`'));
        $this->assertEquals('column', $paginator->exposeNormalizeColumnKey('table.column'));
        $this->assertEquals('column', $paginator->exposeNormalizeColumnKey('`table`.`column`'));
        $this->assertEquals('column', $paginator->exposeNormalizeColumnKey('schema.table.column'));
    }
}
