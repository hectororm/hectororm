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

use Hector\Orm\Pagination\BuilderRangePaginator;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Pagination\RangePagination;
use Hector\Pagination\Request\RangePaginationRequest;

class BuilderRangePaginatorTest extends AbstractTestCase
{
    public function testPaginateReturnsRangePagination(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 4);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(RangePagination::class, $result);
        $this->assertEquals(0, $result->getStart());
        $this->assertEquals(4, $result->getEnd());
    }

    public function testPaginateReturnsEntities(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 2);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        $this->assertNotEmpty($items);
        $this->assertContainsOnlyInstancesOf(Film::class, $items);
    }

    public function testPaginateWithTotal(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderRangePaginator($builder, withTotal: true);
        $request = new RangePaginationRequest(start: 0, end: 4);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderRangePaginator($builder, withTotal: false);
        $request = new RangePaginationRequest(start: 0, end: 4);

        $result = $paginator->paginate($request);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateDifferentRanges(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderRangePaginator($builder, withTotal: false);

        $firstRange = $paginator->paginate(new RangePaginationRequest(start: 0, end: 1));
        $secondRange = $paginator->paginate(new RangePaginationRequest(start: 2, end: 3));

        $firstItems = $firstRange->getItems();
        $secondItems = $secondRange->getItems();

        $this->assertNotEmpty($firstItems);
        $this->assertNotEmpty($secondItems);
        $this->assertNotEquals($firstItems[0]->film_id, $secondItems[0]->film_id);
    }
}
