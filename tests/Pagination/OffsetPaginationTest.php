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

use Hector\Pagination\OffsetPagination;
use Hector\Pagination\OffsetPaginationInterface;
use Hector\Pagination\PaginationInterface;
use PHPUnit\Framework\TestCase;

class OffsetPaginationTest extends TestCase
{
    public function testImplementsInterfaces(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertInstanceOf(PaginationInterface::class, $pagination);
        $this->assertInstanceOf(OffsetPaginationInterface::class, $pagination);
    }

    public function testEmptyPagination(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertTrue($pagination->isEmpty());
        $this->assertCount(0, $pagination);
        $this->assertSame([], $pagination->getArrayCopy());
    }

    public function testWithItems(): void
    {
        $items = ['a', 'b', 'c'];
        $pagination = new OffsetPagination($items, 10);

        $this->assertFalse($pagination->isEmpty());
        $this->assertCount(3, $pagination);
        $this->assertSame($items, $pagination->getArrayCopy());
    }

    public function testWithGenerator(): void
    {
        $generator = (function () {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $pagination = new OffsetPagination($generator, 10);

        $this->assertSame(['a', 'b', 'c'], $pagination->getArrayCopy());
    }

    public function testGetPerPage(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 15);

        $this->assertSame(15, $pagination->getPerPage());
    }

    public function testGetCurrentPage(): void
    {
        $pagination = new OffsetPagination([], 10, currentPage: 3);

        $this->assertSame(3, $pagination->getCurrentPage());
    }

    public function testGetCurrentPageDefault(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertSame(1, $pagination->getCurrentPage());
    }

    public function testGetOffset(): void
    {
        $pagination = new OffsetPagination([], 10, currentPage: 1);
        $this->assertSame(0, $pagination->getOffset());

        $pagination = new OffsetPagination([], 10, currentPage: 2);
        $this->assertSame(10, $pagination->getOffset());

        $pagination = new OffsetPagination([], 15, currentPage: 3);
        $this->assertSame(30, $pagination->getOffset());
    }

    public function testHasPrevious(): void
    {
        $pagination = new OffsetPagination([], 10, currentPage: 1);
        $this->assertFalse($pagination->hasPrevious());

        $pagination = new OffsetPagination([], 10, currentPage: 2);
        $this->assertTrue($pagination->hasPrevious());
    }

    public function testHasMoreAutoDetect(): void
    {
        // Less items than perPage = no more
        $pagination = new OffsetPagination(['a', 'b'], 10);
        $this->assertFalse($pagination->hasMore());

        // Items count equals perPage = potentially more
        $pagination = new OffsetPagination(range(1, 10), 10);
        $this->assertTrue($pagination->hasMore());
    }

    public function testHasMoreExplicit(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, hasMore: true);
        $this->assertTrue($pagination->hasMore());

        $pagination = new OffsetPagination(range(1, 10), 10, hasMore: false);
        $this->assertFalse($pagination->hasMore());
    }

    public function testIterator(): void
    {
        $items = ['a', 'b', 'c'];
        $pagination = new OffsetPagination($items, 10);

        $result = [];
        foreach ($pagination as $item) {
            $result[] = $item;
        }

        $this->assertSame($items, $result);
    }

    public function testJsonSerialize(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, currentPage: 2, hasMore: true);

        $this->assertSame(
            [
                'data' => ['a', 'b'],
                'per_page' => 10,
                'current_page' => 2,
                'has_more' => true,
            ],
            $pagination->jsonSerialize()
        );
    }

    public function testJsonEncode(): void
    {
        $pagination = new OffsetPagination(['a'], 5, currentPage: 1, hasMore: false);

        $json = json_encode($pagination);

        $this->assertJson($json);
        $this->assertSame(
            '{"data":["a"],"per_page":5,"current_page":1,"has_more":false}',
            $json
        );
    }

    public function testGetTotalNull(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertNull($pagination->getTotal());
    }

    public function testGetTotal(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, total: 100);

        $this->assertSame(100, $pagination->getTotal());
    }

    public function testGetTotalLazy(): void
    {
        $called = 0;
        $pagination = new OffsetPagination([], 10, total: function () use (&$called) {
            $called++;
            return 50;
        });

        $this->assertSame(0, $called);
        $this->assertSame(50, $pagination->getTotal());
        $this->assertSame(1, $called);

        // Second call should use cached value
        $this->assertSame(50, $pagination->getTotal());
        $this->assertSame(1, $called);
    }

    public function testGetTotalPages(): void
    {
        $pagination = new OffsetPagination([], 10, total: 100);
        $this->assertSame(10, $pagination->getTotalPages());

        $pagination = new OffsetPagination([], 10, total: 95);
        $this->assertSame(10, $pagination->getTotalPages());

        $pagination = new OffsetPagination([], 10, total: 101);
        $this->assertSame(11, $pagination->getTotalPages());
    }

    public function testGetTotalPagesNull(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertNull($pagination->getTotalPages());
    }

    public function testGetFirstItem(): void
    {
        $pagination = new OffsetPagination(['a', 'b', 'c'], 10, currentPage: 1);
        $this->assertSame(1, $pagination->getFirstItem());

        $pagination = new OffsetPagination(['a', 'b', 'c'], 10, currentPage: 2);
        $this->assertSame(11, $pagination->getFirstItem());

        $pagination = new OffsetPagination(['a', 'b', 'c'], 15, currentPage: 3);
        $this->assertSame(31, $pagination->getFirstItem());
    }

    public function testGetFirstItemEmpty(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertNull($pagination->getFirstItem());
    }

    public function testGetLastItem(): void
    {
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1);
        $this->assertSame(10, $pagination->getLastItem());

        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 2);
        $this->assertSame(20, $pagination->getLastItem());

        $pagination = new OffsetPagination(range(1, 5), 10, currentPage: 10);
        $this->assertSame(95, $pagination->getLastItem());
    }

    public function testGetLastItemEmpty(): void
    {
        $pagination = new OffsetPagination([], 10);

        $this->assertNull($pagination->getLastItem());
    }

    public function testHasMoreWithTotal(): void
    {
        // Page 1 of 10 pages
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 1, total: 100);
        $this->assertTrue($pagination->hasMore());

        // Last page
        $pagination = new OffsetPagination(range(1, 10), 10, currentPage: 10, total: 100);
        $this->assertFalse($pagination->hasMore());
    }

    public function testJsonSerializeWithTotal(): void
    {
        $pagination = new OffsetPagination(['a', 'b'], 10, currentPage: 2, total: 50);

        $this->assertSame(
            [
                'data' => ['a', 'b'],
                'per_page' => 10,
                'current_page' => 2,
                'has_more' => true,
                'total' => 50,
                'total_pages' => 5,
                'first_item' => 11,
                'last_item' => 12,
            ],
            $pagination->jsonSerialize()
        );
    }
}
