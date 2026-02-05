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

namespace Hector\Pagination\Tests;

use Hector\Pagination\PaginationInterface;
use Hector\Pagination\RangePagination;
use Hector\Pagination\RangePaginationInterface;
use PHPUnit\Framework\TestCase;

class RangePaginationTest extends TestCase
{
    public function testImplementsInterfaces(): void
    {
        $pagination = new RangePagination([], 0, 19);

        $this->assertInstanceOf(PaginationInterface::class, $pagination);
        $this->assertInstanceOf(RangePaginationInterface::class, $pagination);
    }

    public function testGetStart(): void
    {
        $pagination = new RangePagination(['a', 'b'], 10, 29);

        $this->assertSame(10, $pagination->getStart());
    }

    public function testGetEnd(): void
    {
        $pagination = new RangePagination(['a', 'b'], 10, 29);

        $this->assertSame(29, $pagination->getEnd());
    }

    public function testGetPerPageCalculated(): void
    {
        $pagination = new RangePagination([], 0, 19);
        $this->assertSame(20, $pagination->getPerPage());

        $pagination = new RangePagination([], 20, 49);
        $this->assertSame(30, $pagination->getPerPage());

        $pagination = new RangePagination([], 0, 0);
        $this->assertSame(1, $pagination->getPerPage());
    }

    public function testGetTotal(): void
    {
        $pagination = new RangePagination([], 0, 19, total: 1000);
        $this->assertSame(1000, $pagination->getTotal());

        $pagination = new RangePagination([], 0, 19);
        $this->assertNull($pagination->getTotal());
    }

    public function testHasPrevious(): void
    {
        $pagination = new RangePagination([], 0, 19);
        $this->assertFalse($pagination->hasPrevious());

        $pagination = new RangePagination([], 20, 39);
        $this->assertTrue($pagination->hasPrevious());

        $pagination = new RangePagination([], 1, 20);
        $this->assertTrue($pagination->hasPrevious());
    }

    public function testHasMoreWithTotal(): void
    {
        // End at 19, total 1000 = more
        $pagination = new RangePagination(range(0, 19), 0, 19, total: 1000);
        $this->assertTrue($pagination->hasMore());

        // End at 999, total 1000 = no more (0-indexed)
        $pagination = new RangePagination(range(0, 19), 980, 999, total: 1000);
        $this->assertFalse($pagination->hasMore());

        // End at 998, total 1000 = more
        $pagination = new RangePagination(range(0, 18), 980, 998, total: 1000);
        $this->assertTrue($pagination->hasMore());
    }

    public function testHasMoreWithoutTotal(): void
    {
        // Full range returned = assume more
        $pagination = new RangePagination(range(0, 19), 0, 19);
        $this->assertTrue($pagination->hasMore());

        // Partial range returned = no more
        $pagination = new RangePagination(range(0, 9), 0, 19);
        $this->assertFalse($pagination->hasMore());
    }

    public function testGetContentRange(): void
    {
        $pagination = new RangePagination([], 0, 19, total: 1000);
        $this->assertSame('items 0-19/1000', $pagination->getContentRange());

        $pagination = new RangePagination([], 20, 39, total: 100);
        $this->assertSame('items 20-39/100', $pagination->getContentRange());
    }

    public function testGetContentRangeWithoutTotal(): void
    {
        $pagination = new RangePagination([], 0, 19);

        $this->assertSame('items 0-19/*', $pagination->getContentRange());
    }

    public function testGetContentRangeCustomUnit(): void
    {
        $pagination = new RangePagination([], 0, 19, total: 500);

        $this->assertSame('users 0-19/500', $pagination->getContentRange('users'));
        $this->assertSame('bytes 0-19/500', $pagination->getContentRange('bytes'));
    }

    public function testIsEmpty(): void
    {
        $pagination = new RangePagination([], 0, 19);
        $this->assertTrue($pagination->isEmpty());

        $pagination = new RangePagination(['a'], 0, 19);
        $this->assertFalse($pagination->isEmpty());
    }

    public function testCount(): void
    {
        $pagination = new RangePagination(['a', 'b', 'c'], 0, 19);

        $this->assertCount(3, $pagination);
    }

    public function testIterator(): void
    {
        $items = ['a', 'b', 'c'];
        $pagination = new RangePagination($items, 0, 19);

        $result = [];
        foreach ($pagination as $item) {
            $result[] = $item;
        }

        $this->assertSame($items, $result);
    }

    public function testJsonSerialize(): void
    {
        $pagination = new RangePagination(['a', 'b'], 20, 39, total: 100);

        $this->assertSame(
            [
                'data' => ['a', 'b'],
                'start' => 20,
                'end' => 39,
                'per_page' => 20,
                'total' => 100,
                'has_more' => true,
            ],
            $pagination->jsonSerialize()
        );
    }

    public function testJsonSerializeWithoutTotal(): void
    {
        $pagination = new RangePagination(['a'], 0, 9);

        $json = $pagination->jsonSerialize();

        $this->assertNull($json['total']);
    }

    public function testJsonEncode(): void
    {
        $pagination = new RangePagination(['x'], 0, 19, total: 50);

        $json = json_encode($pagination);

        $this->assertJson($json);
        $this->assertStringContainsString('"start":0', $json);
        $this->assertStringContainsString('"end":19', $json);
    }
}
