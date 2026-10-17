<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Tests;

use Generator;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverInfo;
use Hector\Pagination\CursorPagination;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Query\Delete;
use Hector\Query\QueryBuilder;
use Hector\Query\Select;
use Hector\Query\Statement\Decrement;
use Hector\Query\Statement\Increment;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class QueryBuilderTest extends TestCase
{
    private ?Connection $connection = null;

    /**
     * Get connection.
     *
     * @return Connection
     */
    private function getConnection(): Connection
    {
        if (null !== $this->connection) {
            return $this->connection;
        }

        return $this->connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
    }

    public function testFetchOne(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->columns('*')->fetchOne();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('table_id', $result);
    }

    public function testFetchOneWithLock(): void
    {
        $connection = new class('sqlite:' . realpath(__DIR__ . '/test.sqlite')) extends Connection {
            public ?string $lastStatement = null;

            public function getDriverInfo(): DriverInfo
            {
                return new DriverInfo('mysql', '8.0.0');
            }

            public function fetchOne(string $statement, BindParamList|array $input_parameters = []): ?array
            {
                $this->lastStatement = $statement;
                return null;
            }
        };
        $queryBuilder = new QueryBuilder($connection);
        $queryBuilder->from('`table`')->columns('*')->fetchOne(true, true);

        $this->assertEquals('SELECT * FROM `table` FOR UPDATE SKIP LOCKED', $connection->lastStatement);
    }

    public function testFetchWithLockWithoutExplicitSkipLockedIsDeprecated(): void
    {
        $connection = new class('sqlite:' . realpath(__DIR__ . '/test.sqlite')) extends Connection {
            public array $statements = [];

            public function getDriverInfo(): DriverInfo
            {
                return new DriverInfo('mysql', '8.0.0');
            }

            public function fetchOne(string $statement, BindParamList|array $input_parameters = []): ?array
            {
                $this->statements[] = $statement;
                return null;
            }

            public function fetchAll(string $statement, BindParamList|array $input_parameters = []): array
            {
                $this->statements[] = $statement;
                return [];
            }

            public function fetchColumn(
                string $statement,
                BindParamList|array $input_parameters = [],
                int $column = 0
            ): array {
                $this->statements[] = $statement;
                return [];
            }
        };
        $queryBuilder = new QueryBuilder($connection);
        $queryBuilder->from('`table`')->columns('*');

        $deprecations = [];
        set_error_handler(
            function (int $errno, string $errstr) use (&$deprecations): bool {
                $deprecations[] = $errstr;
                return true;
            },
            E_USER_DEPRECATED
        );

        try {
            $queryBuilder->fetchOne(lock: false);
            $queryBuilder->fetchOne(lock: true);
            iterator_to_array($queryBuilder->fetchAll(lock: true));
            iterator_to_array($queryBuilder->fetchColumn(lock: true));
        } finally {
            restore_error_handler();
        }

        // Implicit skipLocked keeps the current behavior (SKIP LOCKED) but is deprecated,
        // only when locking is requested.
        $this->assertCount(3, $deprecations);
        $this->assertStringContainsString('$skipLocked', $deprecations[0]);
        $this->assertSame(
            [
                'SELECT * FROM `table`',
                'SELECT * FROM `table` FOR UPDATE SKIP LOCKED',
                'SELECT * FROM `table` FOR UPDATE SKIP LOCKED',
                'SELECT * FROM `table` FOR UPDATE SKIP LOCKED',
            ],
            $connection->statements,
        );
    }

    public function testFetchWithLockWithoutSkipLocked(): void
    {
        $connection = new class('sqlite:' . realpath(__DIR__ . '/test.sqlite')) extends Connection {
            public array $statements = [];

            public function getDriverInfo(): DriverInfo
            {
                return new DriverInfo('mysql', '8.0.0');
            }

            public function fetchOne(string $statement, BindParamList|array $input_parameters = []): ?array
            {
                $this->statements[] = $statement;
                return null;
            }

            public function fetchAll(string $statement, BindParamList|array $input_parameters = []): array
            {
                $this->statements[] = $statement;
                return [];
            }

            public function fetchColumn(
                string $statement,
                BindParamList|array $input_parameters = [],
                int $column = 0
            ): array {
                $this->statements[] = $statement;
                return [];
            }
        };
        $queryBuilder = new QueryBuilder($connection);
        $queryBuilder->from('`table`')->columns('*');

        $queryBuilder->fetchOne(lock: true, skipLocked: false);
        iterator_to_array($queryBuilder->fetchAll(lock: true, skipLocked: false));
        iterator_to_array($queryBuilder->fetchColumn(lock: true, skipLocked: false));
        $queryBuilder->fetchOne(lock: false, skipLocked: false);

        $this->assertSame(
            [
                'SELECT * FROM `table` FOR UPDATE',
                'SELECT * FROM `table` FOR UPDATE',
                'SELECT * FROM `table` FOR UPDATE',
                'SELECT * FROM `table`',
            ],
            $connection->statements,
        );
    }

    public function testFetchAll(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->columns('*')->fetchAll();

        $this->assertInstanceOf(Generator::class, $result);
        $this->assertIsIterable($result);

        $result = iterator_to_array($result);
        $this->assertGreaterThanOrEqual(2, $result);
    }

    public function testFetchColumn(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->fetchColumn(1);

        $this->assertInstanceOf(Generator::class, $result);
        $this->assertIsIterable($result);

        $result = iterator_to_array($result);
        $this->assertGreaterThanOrEqual(2, $result);
        $this->assertNotNull($result);
        $this->assertContains("Foo", $result);
        $this->assertContains("Bar", $result);
    }

    public function testSelect(): void
    {
        $reflectionMethod = new ReflectionMethod(QueryBuilder::class, 'makeSelect');
        PHP_VERSION_ID < 80100 && $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->select('foo', 'f');
        $binds = new BindParamList();

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS `f`',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testDistinct(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC')->distinct();
        $binds = new BindParamList();
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT DISTINCT * FROM foo AS `f` ORDER BY bar DESC',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testSelectWithClosureCondition(): void
    {
        $reflectionMethod = new ReflectionMethod(QueryBuilder::class, 'makeSelect');
        PHP_VERSION_ID < 80100 && $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                function ($query): void {
                    $query->where('bar', 'baz');
                }
            );

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = :_h_0 )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = :_h_0 )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testSelectWithClosureConditionWithReturnedValue(): void
    {
        $reflectionMethod = new ReflectionMethod(QueryBuilder::class, 'makeSelect');
        PHP_VERSION_ID < 80100 && $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                fn(): string => 'bar',
                'test'
            );

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = :_h_0',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testSelectWithClosureConditionValue(): void
    {
        $reflectionMethod = new ReflectionMethod(QueryBuilder::class, 'makeSelect');
        PHP_VERSION_ID < 80100 && $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                'bar',
                fn(): string => 'test'
            );

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = :_h_0',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testMakeSelect(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC');
        $binds = new BindParamList();
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS `f` ORDER BY bar DESC',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS `f` ) AS `countable`',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount_withDistinct(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binds = new BindParamList();

        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS `f` ) AS `countable`',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);

        $queryBuilder->distinct();
        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT DISTINCT f.bar FROM foo AS `f` ) AS `countable`',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount_withHaving(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->having('f.bar = 1')
            ->limit(2);
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT f.bar FROM foo AS `f` HAVING f.bar = 1 ) AS `countable`',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount_withGroup(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->groupBy('foo');
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS `f` GROUP BY foo ) AS `countable`',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeExists(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('f.bar', 'baz');

        $binds = new BindParamList();
        $select = $queryBuilder->makeExists();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertEquals(
            'SELECT EXISTS( SELECT 1 FROM foo AS `f` WHERE f.bar = :_h_0 ) AS `exists`',
            $select->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    /**
     * @dataProvider lockForUpdateProvider
     */
    public function testAddLockForUpdateIsDriverAware(
        string $driver,
        string $version,
        bool $skipLocked,
        string $expectedSuffix,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo($driver, $version));

        $queryBuilder = new FakeQueryBuilder($connection);

        $this->assertSame(
            'SELECT * FROM foo' . $expectedSuffix,
            $queryBuilder->addLockForUpdate('SELECT * FROM foo', $skipLocked),
        );
    }

    public function lockForUpdateProvider(): array
    {
        return [
            // MySQL >= 8.0 supports SKIP LOCKED.
            'mysql 8.0' => ['mysql', '8.0.30', true, ' FOR UPDATE SKIP LOCKED'],
            // MySQL < 8.0 locks rows but has no SKIP LOCKED: must still emit a plain FOR UPDATE
            // (it previously emitted nothing, silently dropping the lock).
            'mysql 5.7' => ['mysql', '5.7.40', true, ' FOR UPDATE'],
            // SQLite has no row-level locking clause: no suffix.
            'sqlite' => ['sqlite', '3.45.0', true, ''],
            // Without skipLocked, a blocking FOR UPDATE is emitted even if SKIP LOCKED is supported.
            'mysql 8.0 without skip locked' => ['mysql', '8.0.30', false, ' FOR UPDATE'],
            'mysql 5.7 without skip locked' => ['mysql', '5.7.40', false, ' FOR UPDATE'],
            'mariadb 10.6 without skip locked' => ['mariadb', '10.6.0', false, ' FOR UPDATE'],
            'pgsql without skip locked' => ['pgsql', '16.0', false, ' FOR UPDATE'],
            'sqlite without skip locked' => ['sqlite', '3.45.0', false, ''],
        ];
    }

    public function testInsert(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->insert(['bar' => 'bar_value']);

        $this->assertEquals(
            'INSERT INTO foo ( bar ) VALUES ( :_h_0 )',
            $queryBuilder->statement
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $queryBuilder->input_parameters)
        );
    }

    public function testInsertSelect(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->insert((new Select())->from('baz')->where('bar', 'bar_value'));

        $this->assertEquals(
            'INSERT INTO foo SELECT * FROM baz WHERE bar = :_h_0',
            $queryBuilder->statement
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $queryBuilder->input_parameters)
        );
    }

    public function testIgnore(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->ignore(true);
        $queryBuilder->from('foo', 'f');
        $queryBuilder->insert((new Select())->from('baz')->where('bar', 'bar_value'));

        // The connection is SQLite, so the driver-aware ignore syntax is "INSERT OR IGNORE".
        $this->assertEquals(
            'INSERT OR IGNORE INTO foo SELECT * FROM baz WHERE bar = :_h_0',
            $queryBuilder->statement
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $queryBuilder->input_parameters)
        );
    }

    public function testMakeDelete(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('bar', 'bar_value');

        $binds = new BindParamList();
        $delete = $queryBuilder->makeDelete();

        $this->assertInstanceOf(Delete::class, $delete);
        $this->assertEquals(
            'DELETE FROM foo WHERE bar = :_h_0',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testUpdateWithArithmeticValues(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('products');
        $queryBuilder->where('id', 42)->where('stock', '>=', 5);

        $queryBuilder->update(['stock' => new Decrement(5), 'reserved' => new Increment(5)]);

        $this->assertEquals(
            'UPDATE products SET stock = stock - :_h_0, reserved = reserved + :_h_1 ' .
            'WHERE id = :_h_2 AND stock >= :_h_3',
            $queryBuilder->statement
        );
        $this->assertSame(
            ['_h_0' => 5, '_h_1' => 5, '_h_2' => 42, '_h_3' => 5],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $queryBuilder->input_parameters)
        );
    }

    public function testUpdateWithArithmeticValuesKeepsBuilderReusable(): void
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('products');
        $queryBuilder->where('id', 42);
        $bindsBefore = $queryBuilder->getBindParams()->getArrayCopy();

        $queryBuilder->update(['stock' => new Increment(2)]);
        $firstStatement = $queryBuilder->statement;
        $firstParameters = $queryBuilder->input_parameters;

        $queryBuilder->update(['stock' => new Increment(2)]);

        $this->assertSame('UPDATE products SET stock = stock + :_h_0 WHERE id = :_h_1', $firstStatement);
        $this->assertSame($firstStatement, $queryBuilder->statement);
        $this->assertEquals($firstParameters, $queryBuilder->input_parameters);
        $this->assertEquals($bindsBefore, $queryBuilder->getBindParams()->getArrayCopy());

        $queryBuilder->update(['stock' => 0]);
        $this->assertSame('UPDATE products SET stock = :_h_0 WHERE id = :_h_1', $queryBuilder->statement);

        $this->assertSame(
            'SELECT * FROM products WHERE id = :_h_0',
            $queryBuilder->makeSelect()->getStatement(new BindParamList())
        );
    }

    public function testPaginateWithOffsetRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $result = $queryBuilder->paginate(new OffsetPaginationRequest(page: 1, perPage: 2));

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertEquals(2, $result->getPerPage());
    }

    public function testPaginateWithRangeRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $result = $queryBuilder->paginate(new RangePaginationRequest(start: 0, end: 1));

        $this->assertInstanceOf(RangePagination::class, $result);
        $this->assertEquals(0, $result->getStart());
        $this->assertEquals(1, $result->getEnd());
    }

    public function testPaginateWithCursorRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->orderBy('table_id');

        $result = $queryBuilder->paginate(new CursorPaginationRequest(perPage: 2));

        $this->assertInstanceOf(CursorPagination::class, $result);
        $this->assertEquals(2, $result->getPerPage());
    }

    public function testPaginateWithTotalEnabled(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $result = $queryBuilder->paginate(new OffsetPaginationRequest(page: 1, perPage: 1), withTotal: true);

        $this->assertNotNull($result->getTotal());
    }

    public function testPaginateWithTotalDisabled(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $result = $queryBuilder->paginate(new OffsetPaginationRequest(page: 1, perPage: 1), withTotal: false);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateThrowsOnUnsupportedRequestType(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $unsupportedRequest = new class implements PaginationRequestInterface {
            public function getOffset(): int
            {
                return 0;
            }

            public function getLimit(): int
            {
                return 10;
            }

            public function withPerPage(int $perPage): static
            {
                return $this;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pagination request type');
        $queryBuilder->paginate($unsupportedRequest);
    }

    public function testChunkPaginateWithOffsetRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $pages = [];
        $queryBuilder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 1),
            function (array $items, OffsetPagination $pagination) use (&$pages): void {
                $pages[] = $items;
            },
        );

        $this->assertGreaterThanOrEqual(2, count($pages));
        $this->assertCount(1, $pages[0]);
        $this->assertCount(1, $pages[1]);
    }

    public function testChunkPaginateWithRangeRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $pages = [];
        $queryBuilder->chunkPaginate(
            new RangePaginationRequest(start: 0, end: 0),
            function (array $items, RangePagination $pagination) use (&$pages): void {
                $pages[] = $items;
            },
        );

        $this->assertGreaterThanOrEqual(2, count($pages));
        $this->assertCount(1, $pages[0]);
        $this->assertCount(1, $pages[1]);
    }

    public function testChunkPaginateWithCursorRequest(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->orderBy('table_id');

        $pages = [];
        $queryBuilder->chunkPaginate(
            new CursorPaginationRequest(perPage: 1),
            function (array $items, CursorPagination $pagination) use (&$pages): void {
                $pages[] = $items;
            },
        );

        $this->assertGreaterThanOrEqual(2, count($pages));
        $this->assertCount(1, $pages[0]);
        $this->assertCount(1, $pages[1]);
    }

    public function testChunkPaginateStopsOnFalseReturn(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*');

        $pages = [];
        $queryBuilder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 1),
            function (array $items, OffsetPagination $pagination) use (&$pages): bool {
                $pages[] = $items;
                return false;
            },
        );

        $this->assertCount(1, $pages);
    }

    public function testChunkPaginateEmptyResult(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->where('table_id', '>', 9999);

        $callbackCalled = false;
        $queryBuilder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 10),
            function () use (&$callbackCalled): void {
                $callbackCalled = true;
            },
        );

        $this->assertFalse($callbackCalled);
    }

    public function testChunkPaginateWithCursorRequestRespectsBuilderLimit(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->orderBy('table_id')->limit(3);

        $total = 0;
        $pages = 0;
        $queryBuilder->chunkPaginate(
            new CursorPaginationRequest(perPage: 2),
            function (array $items, CursorPagination $pagination) use (&$total, &$pages): void {
                $total += count($items);
                $pages++;
            },
        );

        // limit(3) with perPage 2 → page 1 = 2 items, page 2 = 1 item = 3 total
        $this->assertSame(3, $total);
        $this->assertSame(2, $pages);
    }

    public function testChunkPaginateWithOffsetRequestRespectsBuilderLimit(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->limit(3);

        $total = 0;
        $pages = 0;
        $queryBuilder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 2),
            function (array $items, OffsetPagination $pagination) use (&$total, &$pages): void {
                $total += count($items);
                $pages++;
            },
        );

        // Offset paginator handles the bound internally via getBuilderBounds()
        $this->assertSame(3, $total);
        $this->assertSame(2, $pages);
    }

    public function testChunkPaginateCallbackReceivesArrayItems(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`table`')->columns('*')->orderBy('table_id');

        $itemsTypes = [];
        $rowsCount = 0;
        $queryBuilder->chunkPaginate(
            new CursorPaginationRequest(perPage: 3),
            function (mixed $items, CursorPagination $pagination) use (&$itemsTypes, &$rowsCount): void {
                $itemsTypes[] = is_array($items);
                $rowsCount += count($items);
            },
        );

        $this->assertNotEmpty($itemsTypes);
        $this->assertContainsOnly('bool', $itemsTypes);
        foreach ($itemsTypes as $isArray) {
            $this->assertTrue($isArray, 'Items must be a plain array');
        }
        $this->assertGreaterThan(0, $rowsCount);
    }

    public function testWhereInEmptyExecutesWithoutError(): void
    {
        $queryBuilder = new QueryBuilder($this->getConnection());
        $total = count(iterator_to_array($queryBuilder->from('`table`')->columns('*')->fetchAll()));

        // IN [] is always false: no rows, and valid SQL (no "IN (  )" PDOException).
        $inEmpty = new QueryBuilder($this->getConnection());
        $inResult = iterator_to_array(
            $inEmpty->from('`table`')->columns('*')->whereIn('table_id', [])->fetchAll()
        );
        $this->assertCount(0, $inResult);

        // NOT IN [] is always true: all rows.
        $notInEmpty = new QueryBuilder($this->getConnection());
        $notInResult = iterator_to_array(
            $notInEmpty->from('`table`')->columns('*')->whereNotIn('table_id', [])->fetchAll()
        );
        $this->assertCount($total, $notInResult);
    }
}
