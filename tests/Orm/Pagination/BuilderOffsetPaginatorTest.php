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

use Hector\Orm\Pagination\BuilderOffsetPaginator;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Request\OffsetPaginationRequest;

class BuilderOffsetPaginatorTest extends AbstractTestCase
{
    public function testPaginateReturnsOffsetPagination(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertInstanceOf(OffsetPagination::class, $result);
        $this->assertLessThanOrEqual(5, count($result->getItems()));
        $this->assertEquals(5, $result->getPerPage());
        $this->assertEquals(1, $result->getCurrentPage());
    }

    public function testPaginateReturnsEntities(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 3);

        $result = $paginator->paginate($request);
        $items = $result->getItems();

        $this->assertNotEmpty($items);
        $this->assertContainsOnlyInstancesOf(Film::class, $items);
    }

    public function testPaginateWithTotal(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: true);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertNotNull($result->getTotal());
        $this->assertGreaterThan(0, $result->getTotal());
    }

    public function testPaginateWithoutTotal(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 5);

        $result = $paginator->paginate($request);

        $this->assertNull($result->getTotal());
    }

    public function testPaginateHasMoreDetection(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: false);
        $request = new OffsetPaginationRequest(page: 1, perPage: 1);

        $result = $paginator->paginate($request);

        // Database has many films, so hasMore should be true
        $this->assertTrue($result->hasMore());
    }

    public function testPaginateSecondPage(): void
    {
        $builder = new Builder(Film::class);

        $paginator = new BuilderOffsetPaginator($builder, withTotal: false);
        $firstPage = $paginator->paginate(new OffsetPaginationRequest(page: 1, perPage: 2));
        $secondPage = $paginator->paginate(new OffsetPaginationRequest(page: 2, perPage: 2));

        $firstItems = $firstPage->getItems();
        $secondItems = $secondPage->getItems();

        $this->assertNotEmpty($firstItems);
        $this->assertNotEmpty($secondItems);
        $this->assertNotEquals($firstItems[0]->film_id, $secondItems[0]->film_id);
    }
}
