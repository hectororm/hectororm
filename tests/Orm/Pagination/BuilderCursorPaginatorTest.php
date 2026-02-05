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

namespace Hector\Orm\Tests\Pagination;

use Hector\Orm\Pagination\BuilderCursorPaginator;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Pagination\CursorPagination;
use Hector\Pagination\Request\CursorPaginationRequest;
use InvalidArgumentException;

class BuilderCursorPaginatorTest extends AbstractTestCase
{
    public function testPaginateReturnsCursorPagination(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(CursorPagination::class, $result);
        $this->assertEquals(5, $result->getPerPage());
    }

    public function testPaginateReturnsEntities(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 3);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        $this->assertNotEmpty($items);
        $this->assertContainsOnlyInstancesOf(Film::class, $items);
    }

    public function testPaginateThrowsWithoutOrderBy(): void
    {
        $builder = new Builder(Film::class);
        $builder->resetOrder();

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 5);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ORDER BY');
        $paginator->paginate($request);
    }

    public function testPaginateWithTotal(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: true);
        $request = new CursorPaginationRequest(perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);
        $request = new CursorPaginationRequest(perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateWithPosition(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);

        // First page
        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 2));
        $firstItems = $firstPage->getItems();

        $this->assertNotEmpty($firstItems);

        // Second page using next position
        $nextPosition = $firstPage->getNextPosition();
        $this->assertNotNull($nextPosition);

        $secondPage = $paginator->paginate(new CursorPaginationRequest(perPage: 2, position: $nextPosition));
        $secondItems = $secondPage->getItems();

        $this->assertNotEmpty($secondItems);
        $this->assertNotEquals($firstItems[0]->film_id, $secondItems[0]->film_id);
    }

    public function testExtractCursorPositionFromEntity(): void
    {
        $builder = new Builder(Film::class);
        $builder->orderBy('film_id', 'ASC');

        $paginator = new BuilderCursorPaginator($builder, withTotal: false);
        $firstPage = $paginator->paginate(new CursorPaginationRequest(perPage: 1));

        $this->assertNotNull($firstPage->getNextPosition());
        $this->assertArrayHasKey('film_id', $firstPage->getNextPosition());
    }
}
