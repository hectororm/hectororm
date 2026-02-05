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

use Hector\Pagination\CursorPagination;
use Hector\Pagination\CursorPaginationInterface;
use Hector\Pagination\PaginationInterface;
use PHPUnit\Framework\TestCase;

class CursorPaginationTest extends TestCase
{
    public function testImplementsInterfaces(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertInstanceOf(PaginationInterface::class, $pagination);
        $this->assertInstanceOf(CursorPaginationInterface::class, $pagination);
    }

    public function testEmptyPagination(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertTrue($pagination->isEmpty());
        $this->assertCount(0, $pagination);
    }

    public function testWithItems(): void
    {
        $items = ['a', 'b', 'c'];
        $pagination = new CursorPagination($items, 10);

        $this->assertFalse($pagination->isEmpty());
        $this->assertCount(3, $pagination);
        $this->assertSame($items, $pagination->getArrayCopy());
    }

    public function testGetPerPage(): void
    {
        $pagination = new CursorPagination([], 25);

        $this->assertSame(25, $pagination->getPerPage());
    }

    public function testHasMoreWithNextPosition(): void
    {
        $pagination = new CursorPagination([], 10, nextPosition: ['id' => 42]);

        $this->assertTrue($pagination->hasMore());
    }

    public function testHasMoreWithoutNextPosition(): void
    {
        $pagination = new CursorPagination([], 10, nextPosition: null);

        $this->assertFalse($pagination->hasMore());
    }

    public function testGetNextPosition(): void
    {
        $nextPosition = ['id' => 42, 'created_at' => '2024-01-01'];
        $pagination = new CursorPagination([], 10, nextPosition: $nextPosition);

        $this->assertSame($nextPosition, $pagination->getNextPosition());
    }

    public function testGetNextPositionNull(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertNull($pagination->getNextPosition());
    }

    public function testGetPreviousPosition(): void
    {
        $previousPosition = ['id' => 10];
        $pagination = new CursorPagination([], 10, previousPosition: $previousPosition);

        $this->assertSame($previousPosition, $pagination->getPreviousPosition());
    }

    public function testGetPreviousPositionNull(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertNull($pagination->getPreviousPosition());
    }

    public function testHasPrevious(): void
    {
        $pagination = new CursorPagination([], 10);
        $this->assertFalse($pagination->hasPrevious());

        $pagination = new CursorPagination([], 10, previousPosition: ['id' => 10]);
        $this->assertTrue($pagination->hasPrevious());
    }

    public function testGetCursorName(): void
    {
        $pagination = new CursorPagination([], 10, cursorName: 'my-cursor-123');

        $this->assertSame('my-cursor-123', $pagination->getCursorName());
    }

    public function testGetCursorNameNull(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertNull($pagination->getCursorName());
    }

    public function testIterator(): void
    {
        $items = ['a', 'b', 'c'];
        $pagination = new CursorPagination($items, 10);

        $result = [];
        foreach ($pagination as $item) {
            $result[] = $item;
        }

        $this->assertSame($items, $result);
    }

    public function testJsonSerialize(): void
    {
        $pagination = new CursorPagination(
            items: ['a', 'b'],
            perPage: 10,
            nextPosition: ['id' => 42],
            previousPosition: ['id' => 10],
        );

        $json = $pagination->jsonSerialize();

        $this->assertSame(['a', 'b'], $json['data']);
        $this->assertSame(10, $json['per_page']);
        $this->assertTrue($json['has_more']);
        $this->assertSame(['id' => 42], $json['next_position']);
        $this->assertSame(['id' => 10], $json['previous_position']);
    }

    public function testJsonSerializeNoPositions(): void
    {
        $pagination = new CursorPagination(['a'], 10);

        $json = $pagination->jsonSerialize();

        $this->assertFalse($json['has_more']);
        $this->assertNull($json['next_position']);
        $this->assertNull($json['previous_position']);
    }

    public function testJsonEncode(): void
    {
        $pagination = new CursorPagination(['x'], 5, nextPosition: ['id' => 1]);

        $json = json_encode($pagination);

        $this->assertJson($json);
        $this->assertStringContainsString('"has_more":true', $json);
        $this->assertStringContainsString('"next_position":', $json);
    }

    public function testGetTotalNull(): void
    {
        $pagination = new CursorPagination([], 10);

        $this->assertNull($pagination->getTotal());
    }

    public function testGetTotal(): void
    {
        $pagination = new CursorPagination([], 10, total: 100);

        $this->assertSame(100, $pagination->getTotal());
    }

    public function testGetTotalLazy(): void
    {
        $called = 0;
        $pagination = new CursorPagination([], 10, total: function () use (&$called) {
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

    public function testJsonSerializeWithTotal(): void
    {
        $pagination = new CursorPagination(
            items: ['a', 'b'],
            perPage: 10,
            nextPosition: ['id' => 42],
            total: 100,
        );

        $json = $pagination->jsonSerialize();

        $this->assertSame(100, $json['total']);
    }

    public function testJsonSerializeWithoutTotal(): void
    {
        $pagination = new CursorPagination(['a'], 10);

        $json = $pagination->jsonSerialize();

        $this->assertArrayNotHasKey('total', $json);
    }
}
