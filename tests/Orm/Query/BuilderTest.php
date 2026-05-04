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

namespace Hector\Orm\Tests\Query;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Log\LogEntry;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Collection\LazyCollection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\NotFoundException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use Hector\Orm\Tests\Fake\Entity\Staff;
use Hector\Pagination\CursorPagination;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\PaginationInterface;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\CursorPaginationRequest;
use Hector\Pagination\Request\OffsetPaginationRequest;
use Hector\Pagination\Request\PaginationRequestInterface;
use Hector\Pagination\Request\RangePaginationRequest;
use Hector\Query\Component\Conditions;
use Hector\Query\Component\Limit;
use Hector\Query\Component\Order;
use InvalidArgumentException;

class BuilderTest extends AbstractTestCase
{
    public function testConstruct(): void
    {
        $builder = new Builder(Staff::class);
        $binds = new BindParamList();

        $this->assertInstanceOf(Conditions::class, $builder->where);
        $this->assertInstanceOf(Order::class, $builder->order);
        $this->assertInstanceOf(Limit::class, $builder->limit);
        $this->assertNull($builder->where->getStatement($binds));
        $this->assertNull($builder->order->getStatement($binds));
        $this->assertNull($builder->limit->getStatement($binds));
    }

    public function testWith(): void
    {
        $builder = new Builder(Staff::class);
        $builder->with($with = ['address' => ['city']]);

        $this->assertEquals($with, $builder->with);
    }

    public function testGetOffset0(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(0);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testGetOffset1(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(2, $entity->staff_id);
    }

    public function testGetOffsetNonexistent(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(99999);

        $this->assertNull($entity);
    }

    public function testGetOrFailSuccess(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrFail(0);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testGetOrFailException(): void
    {
        $builder = new Builder(Staff::class);

        $this->expectException(NotFoundException::class);
        $builder->getOrFail(9999);
    }

    public function testGetOrNewExistent(): void
    {
        $builder = new Builder(Staff::class);

        $entity = $builder->getOrNew(0);
        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNotNull($entity->staff_id);
    }

    public function testGetOrNewNonexistent(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrNew(9999);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertNull($entity->last_name);
        $this->assertNull($entity->first_name);
    }

    public function testGetOrNewWithData(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrNew(9999, ['first_name' => 'Foo', 'last_name' => 'Bar']);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertEquals('Foo', $entity->first_name);
        $this->assertEquals('Bar', $entity->last_name);
    }

    public function testFindByPrimaryWithMultipleKey(): void
    {
        $this->expectException(MapperException::class);

        $builder = new Builder(Staff::class);
        $builder->find([99999, 1]);
    }

    public function testFindMultipleWithConditionWithTwoEntity(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 1], ['staff_id' => 2]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testFindMultipleWithConditionWithTwoEntity_onlyValues(): void
    {
        $builder = new Builder(Language::class);
        $collection = $builder->find(...[1, 2, 3, 4]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(4, $collection);
    }

    public function testFindMultipleWithConditionWithThreeEntityAndOneNonexistent(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 1], ['staff_id' => 2], ['staff_id' => 99999]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testFindMultipleWithNonexistentEntity(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 99998], ['staff_id' => 99999]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testFind1(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testFind2(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(2);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(2, $entity->staff_id);
    }

    public function testFindOffsetNonexistent(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(99999);

        $this->assertNull($entity);
    }

    public function testFindOrFailSuccess(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrFail(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testFindOrFailException(): void
    {
        $builder = new Builder(Staff::class);

        $this->expectException(NotFoundException::class);
        $builder->findOrFail(9999);
    }

    public function testFindOrNewExistent(): void
    {
        $builder = new Builder(Staff::class);

        $entity = $builder->findOrNew(1);
        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNotNull($entity->staff_id);
    }

    public function testFindOrNewNonexistent(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrNew(9999);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertNull($entity->last_name);
        $this->assertNull($entity->first_name);
    }

    public function testFindOrNewWithData(): void
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrNew(9999, ['first_name' => 'Foo', 'last_name' => 'Bar']);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertEquals('Foo', $entity->first_name);
        $this->assertEquals('Bar', $entity->last_name);
    }

    public function testAll(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertGreaterThanOrEqual(2, count($collection));
    }

    public function testAllWithRelations(): void
    {
        $nbQueriesBefore = count($this->getOrm()->getConnection()->getLogger());
        $builder = new Builder(Staff::class);
        $collection = $builder->with(['address' => ['city' => ['country']]])->whereIn('staff_id', [1, 2])->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);

        /** @var Entity $entity */
        foreach ($collection as $entity) {
            $this->assertTrue(isset($entity->getRelated()->address));
        }

        $this->assertEquals(4, count($this->getOrm()->getConnection()->getLogger()) - $nbQueriesBefore);
    }

    public function testAllWithCondition(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->where('staff_id', '=', 1)->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(1, $collection);
    }

    public function testAllWithConditionNoResult(): void
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->where('staff_id', '>', 9999)->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testChunk(): void
    {
        $builder = new Builder(Film::class);
        $total = 0;
        $builder->chunk(
            50,
            function (Collection $collection) use (&$total): void {
                $this->assertGreaterThan(0, count($collection));
                $this->assertLessThanOrEqual(50, count($collection));
                $this->assertContainsOnlyInstancesOf(Film::class, $collection);
                $total += count($collection);
            }
        );

        $this->assertEquals($builder->count(), $total);
    }

    public function testChunk_eager(): void
    {
        $builder = new Builder(Film::class);
        $total = 0;
        $builder->chunk(
            50,
            function (Collection $collection) use (&$total): void {
                $this->assertGreaterThan(0, count($collection));
                $this->assertLessThanOrEqual(50, count($collection));
                $this->assertContainsOnlyInstancesOf(Film::class, $collection);
                $total += count($collection);
            },
            false
        );

        $this->assertEquals($builder->count(), $total);
    }

    public function testChunkPaginateWithOffsetRequest(): void
    {
        $builder = new Builder(Film::class);
        $total = 0;
        $pages = 0;

        $builder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 100),
            function (PaginationInterface $pagination) use (&$total, &$pages): void {
                $this->assertInstanceOf(OffsetPagination::class, $pagination);
                $this->assertContainsOnlyInstancesOf(Film::class, $pagination->getItems());
                $total += count($pagination);
                $pages++;
            },
        );

        $this->assertEquals($builder->count(), $total);
        $this->assertGreaterThan(1, $pages);
    }

    public function testChunkPaginateWithCursorRequest(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');
        $total = 0;
        $pages = 0;

        $builder->chunkPaginate(
            new CursorPaginationRequest(perPage: 100),
            function (PaginationInterface $pagination) use (&$total, &$pages): void {
                $this->assertInstanceOf(CursorPagination::class, $pagination);
                $this->assertContainsOnlyInstancesOf(Film::class, $pagination->getItems());
                $total += count($pagination);
                $pages++;
            },
        );

        $this->assertEquals($builder->count(), $total);
        $this->assertGreaterThan(1, $pages);
    }

    public function testChunkPaginateWithRangeRequest(): void
    {
        $builder = new Builder(Film::class);
        $total = 0;
        $pages = 0;

        $builder->chunkPaginate(
            new RangePaginationRequest(start: 0, end: 99),
            function (PaginationInterface $pagination) use (&$total, &$pages): void {
                $this->assertInstanceOf(RangePagination::class, $pagination);
                $this->assertContainsOnlyInstancesOf(Film::class, $pagination->getItems());
                $total += count($pagination);
                $pages++;
            },
        );

        $this->assertEquals($builder->count(), $total);
        $this->assertGreaterThan(1, $pages);
    }

    public function testChunkPaginateStopsOnFalseReturn(): void
    {
        $builder = new Builder(Film::class);
        $pages = 0;

        $builder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 100),
            function () use (&$pages): bool {
                $pages++;
                return false;
            },
        );

        $this->assertSame(1, $pages);
    }

    public function testChunkPaginateEmptyResult(): void
    {
        $builder = new Builder(Film::class);
        $builder->where('film_id', '>', 999999);
        $callbackCalled = false;

        $builder->chunkPaginate(
            new OffsetPaginationRequest(page: 1, perPage: 10),
            function () use (&$callbackCalled): void {
                $callbackCalled = true;
            },
        );

        $this->assertFalse($callbackCalled);
    }

    public function testYield(): void
    {
        $builder = new Builder(Film::class);
        $iterator = $builder->yield();

        $this->assertInstanceOf(LazyCollection::class, $iterator);
        $this->assertCount($builder->count(), $iterator);
    }

    public function testCount(): void
    {
        $builder = new Builder(Film::class);
        $total = $builder->count();

        $this->assertGreaterThan(0, $total);
    }

    public function testWhereWithNamedRelations(): void
    {
        Orm::$alias = 0;
        $builder = new Builder(Film::class);
        $builder->where('language.name', 'French');
        $builder->orWhere('language.name', 'Italian');
        $builder->where('original_language.name', 'French');

        $this->assertEquals(
            'SELECT DISTINCT `main`.`film_id`, `main`.`title`, `main`.`description`, `main`.`release_year`, `main`.`language_id`, `main`.`original_language_id`, `main`.`rental_duration`, `main`.`rental_rate`, `main`.`length`, `main`.`replacement_cost`, `main`.`rating`, `main`.`special_features`, `main`.`last_update` ' .
            'FROM `sakila`.`film` AS `main` ' .
            'LEFT JOIN `sakila`.`language` AS `language` ON ( `main`.`language_id` = `language`.`language_id` ) ' .
            'LEFT JOIN `sakila`.`language` AS `original_language` ON ( `main`.`original_language_id` = `original_language`.`language_id` ) ' .
            'WHERE `language`.`name` = :_h_0 OR `language`.`name` = :_h_1 AND `original_language`.`name` = :_h_2',
            $builder->getStatement(new BindParamList()));
    }

    public function testEncapsuledWhereWithNamedRelations(): void
    {
        Orm::$alias = 0;
        $builder = new Builder(Film::class);
        $builder->where(function ($where): void {
            $where->where('language.name', 'French');
            $where->orWhere('language.name', 'Italian');
        });

        $statement = $builder->getStatement(new BindParamList());
        $this->assertEquals(
            'SELECT DISTINCT `main`.`film_id`, `main`.`title`, `main`.`description`, `main`.`release_year`, `main`.`language_id`, `main`.`original_language_id`, `main`.`rental_duration`, `main`.`rental_rate`, `main`.`length`, `main`.`replacement_cost`, `main`.`rating`, `main`.`special_features`, `main`.`last_update` ' .
            'FROM `sakila`.`film` AS `main` ' .
            'LEFT JOIN `sakila`.`language` AS `language` ON ( `main`.`language_id` = `language`.`language_id` ) ' .
            'WHERE ( `language`.`name` = :_h_0 OR `language`.`name` = :_h_1 )',
            $statement);
    }

    public function testEncapsuledWhere(): void
    {
        Orm::$alias = 0;
        $builder = new Builder(Film::class);
        $builder->where(function (): void {
            $this->where('language.name', 'French');
            $this->orWhere('language.name', 'Italian');
        });

        $statement = $builder->getStatement(new BindParamList());
        $this->assertEquals(
            'SELECT DISTINCT `main`.`film_id`, `main`.`title`, `main`.`description`, `main`.`release_year`, `main`.`language_id`, `main`.`original_language_id`, `main`.`rental_duration`, `main`.`rental_rate`, `main`.`length`, `main`.`replacement_cost`, `main`.`rating`, `main`.`special_features`, `main`.`last_update` ' .
            'FROM `sakila`.`film` AS `main` ' .
            'LEFT JOIN `sakila`.`language` AS `language` ON ( `main`.`language_id` = `language`.`language_id` ) ' .
            'WHERE `language`.`name` = :_h_0 OR `language`.`name` = :_h_1',
            $statement);
    }

    public function testWhereWithDeepNamedRelations(): void
    {
        Orm::$alias = 0;
        $builder = new Builder(Staff::class);
        $builder->where('address.city.city', 'Paris');
        $builder->where('address.city.country_id', 1);

        $this->assertEquals(
            'SELECT DISTINCT `main`.`staff_id`, `main`.`first_name`, `main`.`last_name`, `main`.`address_id`, `main`.`picture`, `main`.`email`, `main`.`store_id`, `main`.`active`, `main`.`username`, `main`.`password`, `main`.`last_update` ' .
            'FROM `sakila`.`staff` AS `main` ' .
            'LEFT JOIN `sakila`.`address` AS `address` ON ( `main`.`address_id` = `address`.`address_id` ) ' .
            'LEFT JOIN `sakila`.`city` AS `address#city` ON ( `address`.`city_id` = `address#city`.`city_id` ) ' .
            'WHERE `address#city`.`city` = :_h_0 AND `address#city`.`country_id` = :_h_1',
            $builder->getStatement(new BindParamList()));
    }

    public function testPaginateWithOffsetRequest(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $builder->paginate($request);

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertLessThanOrEqual(5, count($result->getItems()));
        $this->assertEquals(5, $result->getPerPage());
        $this->assertEquals(1, $result->getCurrentPage());
        $this->assertContainsOnlyInstancesOf(Film::class, $result->getItems());
    }

    public function testPaginateWithCursorRequest(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');
        $request = new CursorPaginationRequest(perPage: 5);

        $result = $builder->paginate($request);

        $this->assertInstanceOf(CursorPagination::class, $result);
        $this->assertEquals(5, $result->getPerPage());
        $this->assertContainsOnlyInstancesOf(Film::class, $result->getItems());
    }

    public function testPaginateWithRangeRequest(): void
    {
        $builder = new Builder(Film::class);
        $request = new RangePaginationRequest(start: 0, end: 4);

        $result = $builder->paginate($request);

        $this->assertInstanceOf(RangePagination::class, $result);
        $this->assertEquals(0, $result->getStart());
        $this->assertEquals(4, $result->getEnd());
        $this->assertContainsOnlyInstancesOf(Film::class, $result->getItems());
    }

    public function testPaginateWithTotal(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $builder->paginate($request, withTotal: true);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $builder->paginate($request, withTotal: false);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateWithUnsupportedRequestThrows(): void
    {
        $builder = new Builder(Film::class);
        $request = new class implements PaginationRequestInterface {
            public function getLimit(): int
            {
                return 10;
            }

            public function getOffset(): int
            {
                return 0;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pagination request type');
        $builder->paginate($request);
    }

    public function testPaginateOptimizedWithOffsetRequest(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 2, perPage: 5);

        $result = $builder->paginate($request, optimized: true);

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertLessThanOrEqual(5, count($result));
        $this->assertSame(5, $result->getPerPage());
        $this->assertSame(2, $result->getCurrentPage());

        // Must return Entity instances, not raw arrays
        foreach ($result as $item) {
            $this->assertInstanceOf(Film::class, $item);
        }
    }

    public function testPaginateOptimizedWithCursorRequest(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');
        $request = new CursorPaginationRequest(perPage: 5);

        $result = $builder->paginate($request, optimized: true);

        $this->assertInstanceOf(CursorPagination::class, $result);
        $this->assertSame(5, $result->getPerPage());
        $this->assertCount(5, $result);

        // Must return Entity instances
        foreach ($result as $item) {
            $this->assertInstanceOf(Film::class, $item);
        }

        // Must have cursor positions
        $this->assertNotNull($result->getNextPosition());
    }

    public function testPaginateOptimizedWithRangeRequest(): void
    {
        $builder = new Builder(Film::class);
        $request = new RangePaginationRequest(start: 0, end: 4);

        $result = $builder->paginate($request, optimized: true);

        $this->assertInstanceOf(RangePagination::class, $result);
        $this->assertSame(0, $result->getStart());
        $this->assertSame(4, $result->getEnd());

        // Must return Entity instances
        foreach ($result as $item) {
            $this->assertInstanceOf(Film::class, $item);
        }
    }

    public function testPaginateOptimizedReturnsCorrectCount(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 1, perPage: 10);

        $normal = (new Builder(Film::class))->paginate($request);
        $optimized = (new Builder(Film::class))->paginate($request, optimized: true);

        // Both should return the same count
        $this->assertCount(count($normal), $optimized);
    }

    public function testPaginateOptimizedWithTotal(): void
    {
        $builder = new Builder(Film::class);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $builder->paginate($request, withTotal: true, optimized: true);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function testPaginateOptimizedPreservesOrder(): void
    {
        $request = new OffsetPaginationRequest(page: 1, perPage: 10);

        $normal = (new Builder(Film::class))->orderBy('film_id', 'DESC')->paginate($request);
        $optimized = (new Builder(Film::class))->orderBy('film_id', 'DESC')->paginate($request, optimized: true);

        $normalIds = array_map(fn(Film $f): ?int => $f->film_id, $normal->getArrayCopy());
        $optimizedIds = array_map(fn(Film $f): ?int => $f->film_id, $optimized->getArrayCopy());

        $this->assertSame($normalIds, $optimizedIds);
    }

    public function testPaginateOptimizedWithJoin(): void
    {
        $builder = new Builder(Film::class);
        $builder->where('language.name', 'English');
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $builder->paginate($request, optimized: true);

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertLessThanOrEqual(5, count($result));

        foreach ($result as $item) {
            $this->assertInstanceOf(Film::class, $item);
        }
    }

    public function testPaginateOptimizedCursorNavigation(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        // First page
        $request1 = new CursorPaginationRequest(perPage: 3);
        $page1 = $builder->paginate($request1, optimized: true);

        $this->assertCount(3, $page1);
        $this->assertNotNull($page1->getNextPosition());

        // Second page using cursor from first page
        $request2 = new CursorPaginationRequest(perPage: 3, position: $page1->getNextPosition());
        $page2 = (new Builder(Film::class))->orderBy('film_id', 'ASC')->paginate($request2, optimized: true);

        $this->assertCount(3, $page2);

        // Pages must not overlap
        $page1Ids = array_map(fn(Film $f): ?int => $f->film_id, $page1->getArrayCopy());
        $page2Ids = array_map(fn(Film $f): ?int => $f->film_id, $page2->getArrayCopy());

        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));
        // Page 2 IDs should be strictly after page 1 IDs
        $this->assertGreaterThan(max($page1Ids), min($page2Ids));
    }

    public function testPaginateOptimizedSqlQuery(): void
    {
        $logger = $this->getOrm()->getConnection()->getLogger();
        $nbQueriesBefore = count($logger);

        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');
        $request = new OffsetPaginationRequest(page: 2, perPage: 5);
        $builder->paginate($request, optimized: true);

        $queryLogs = array_values(array_filter(
            array_slice($logger->getLogs(), $nbQueriesBefore),
            fn(LogEntry $l): bool => $l->getType() === LogEntry::TYPE_QUERY,
        ));

        // Single query with INNER JOIN derived table
        $this->assertCount(1, $queryLogs);
        $this->assertSame(
            'SELECT `main`.`film_id`, `main`.`title`, `main`.`description`, `main`.`release_year`, '
            . '`main`.`language_id`, `main`.`original_language_id`, `main`.`rental_duration`, '
            . '`main`.`rental_rate`, `main`.`length`, `main`.`replacement_cost`, `main`.`rating`, '
            . '`main`.`special_features`, `main`.`last_update` '
            . 'FROM `sakila`.`film` AS `main` '
            . 'INNER JOIN ( SELECT DISTINCT `main`.`film_id` '
            . 'FROM `sakila`.`film` AS `main` '
            . 'ORDER BY film_id ASC '
            . 'LIMIT 6 OFFSET 5 ) AS `pagination` '
            . 'ON ( `main`.`film_id` = `pagination`.`film_id` ) '
            . 'ORDER BY film_id ASC',
            $queryLogs[0]->getStatement(),
        );
    }

    public function testPaginateNormalSqlQuery(): void
    {
        $logger = $this->getOrm()->getConnection()->getLogger();
        $nbQueriesBefore = count($logger);

        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');
        $request = new OffsetPaginationRequest(page: 2, perPage: 5);
        $builder->paginate($request);

        $queryLogs = array_values(array_filter(
            array_slice($logger->getLogs(), $nbQueriesBefore),
            fn(LogEntry $l): bool => $l->getType() === LogEntry::TYPE_QUERY,
        ));

        // Single query without INNER JOIN
        $this->assertCount(1, $queryLogs);
        $this->assertSame(
            'SELECT `main`.`film_id`, `main`.`title`, `main`.`description`, `main`.`release_year`, '
            . '`main`.`language_id`, `main`.`original_language_id`, `main`.`rental_duration`, '
            . '`main`.`rental_rate`, `main`.`length`, `main`.`replacement_cost`, `main`.`rating`, '
            . '`main`.`special_features`, `main`.`last_update` '
            . 'FROM `sakila`.`film` AS `main` '
            . 'ORDER BY film_id ASC '
            . 'LIMIT 6 OFFSET 5',
            $queryLogs[0]->getStatement(),
        );
    }

    public function testPaginateOptimizedAndNormalReturnSameData(): void
    {
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $normal = (new Builder(Film::class))->orderBy('film_id', 'ASC')->paginate($request);
        $optimized = (new Builder(Film::class))->orderBy('film_id', 'ASC')->paginate($request, optimized: true);

        $normalIds = array_map(fn(Film $f): ?int => $f->film_id, $normal->getArrayCopy());
        $optimizedIds = array_map(fn(Film $f): ?int => $f->film_id, $optimized->getArrayCopy());

        // Same entities, same order
        $this->assertSame($normalIds, $optimizedIds);
        $this->assertCount(count($normal), $optimized);
    }
}
