<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Collection\Tests;

use ArrayIterator;
use DateTime;
use Hector\Collection\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;

class CollectionTest extends TestCase
{
    public function testCount()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(count($arr), count($collection));
    }

    public function testGetIterator()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf(ArrayIterator::class, $collection->getIterator());
        $this->assertSame($collection->getArrayCopy(), $collection->getIterator()->getArrayCopy());
    }

    public function testArrayAccess()
    {
        $collection = new Collection(['key1' => 'foo', 'bar', 'key2' => 'baz', 'qux', 'quxx']);
        $collection[] = 'value1';
        $collection['key3'] = 'value2';

        unset($collection['nonexistent']);
        unset($collection['key1']);

        $this->assertCount(6, $collection);
        $this->assertTrue(isset($collection['key2']));
        $this->assertTrue(isset($collection['key3']));
        $this->assertFalse(isset($collection['key1']));
        $this->assertEquals('value2', $collection['key3']);
        $this->assertEquals('value1', $collection[3]);
        $this->assertNull($collection['nonexistent']);
    }

    public function testGetArrayCopy()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->getArrayCopy());
    }

    public function testGetArrayCopy_recursive()
    {
        $collection = new Collection([
            new Collection($arr1 = ['foo', 'bar', 'baz']),
            new Collection($arr2 = ['qux', 'quxx'])
        ]);

        $this->assertSame([$arr1, $arr2], $collection->getArrayCopy());
    }

    public function testDebugInfo()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->__debugInfo());
    }

    public function testJsonSerialize()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->jsonSerialize());
    }

    public function testAppend()
    {
        $collection = new Collection(['foo', 'bar']);

        $this->assertSame(
            ['foo', 'bar', 'baz', 'qux', 'quxx'],
            $collection->append('baz', 'qux', 'quxx')->getArrayCopy()
        );
    }

    public function testPrepend()
    {
        $collection = new Collection(['foo', 'bar']);

        $this->assertSame(
            ['baz', 'qux', 'quxx', 'foo', 'bar'],
            $collection->prepend('baz', 'qux', 'quxx')->getArrayCopy()
        );
    }

    public function testSort()
    {
        $collection = new Collection(
            $arr = ['d' => 'Lemon', 'a' => 'orange', 'b1' => 'banana10', 'b2' => 'banana2', 'c' => 'apple']
        );

        $this->assertSame(
            ['d' => 'Lemon', 'c' => 'apple', 'b1' => 'banana10', 'b2' => 'banana2', 'a' => 'orange'],
            $collection->sort()->getArrayCopy()
        );
        $this->assertSame(
            ['d' => 'Lemon', 'c' => 'apple', 'b2' => 'banana2', 'b1' => 'banana10', 'a' => 'orange'],
            $collection->sort(SORT_NATURAL)->getArrayCopy()
        );
        $this->assertSame(
            ['c' => 'apple', 'b1' => 'banana10', 'b2' => 'banana2', 'd' => 'Lemon', 'a' => 'orange'],
            $collection->sort('strcasecmp')->getArrayCopy()
        );

        $this->assertSame(
            ['d' => 'Lemon', 'a' => 'orange', 'b1' => 'banana10', 'b2' => 'banana2', 'c' => 'apple'],
            $arr
        );
    }

    public function testFilter()
    {
        $collection = new Collection(['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(
            ['foo', 3 => 'qux'],
            $collection->filter(fn($value) => in_array($value, ['foo', 'qux']))->getArrayCopy()
        );
    }

    public function testFilterInstanceOf()
    {
        $collection = new Collection([
            $value1 = new stdClass(),
            $value2 = new class extends stdClass {
            },
            new Collection(),
            $value4 = new DateTime(),
            'string',
        ]);

        $this->assertSame([3 => $value4], $collection->filterInstanceOf(DateTime::class)->getArrayCopy());
        $this->assertSame([$value1, $value2], $collection->filterInstanceOf(stdClass::class)->getArrayCopy());
        $this->assertSame(
            [$value1, $value2, []],
            $collection->filterInstanceOf(stdClass::class, new Collection())->getArrayCopy()
        );
    }

    public function testMap()
    {
        $callback = fn($value) => $value . 'Mapped';
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(array_map($callback, $arr), $collection->map($callback)->getArrayCopy());
    }

    public function testFirst()
    {
        $callback = fn($value) => str_starts_with($value, 'ba');
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertEquals('bar', $collection->search($callback));
        $this->assertEquals('bar', $collection->first($callback));
        $this->assertEquals('foo', $collection->first());

        $this->assertNull((new Collection())->first($callback));
        $this->assertNull((new Collection())->first());
    }

    public function testLast()
    {
        $callback = fn($value) => str_starts_with($value, 'ba');
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertEquals('baz', $collection->last($callback));
        $this->assertEquals('quxx', $collection->last());

        $this->assertNull((new Collection())->last($callback));
        $this->assertNull((new Collection())->last());
    }

    public function testContains()
    {
        $collection = new Collection($arr = ['1', '2', 1, 3, 4]);

        $this->assertTrue($collection->contains(2, false));
        $this->assertFalse($collection->contains(2, true));
        $this->assertTrue($collection->contains('1', false));
        $this->assertTrue($collection->contains(1, true));

        $this->assertSame(in_array(2, $arr, false), $collection->contains(2, false));
        $this->assertSame(in_array(2, $arr, true), $collection->contains(2, true));
        $this->assertSame(in_array(1, $arr, false), $collection->contains(1, false));
        $this->assertSame(in_array(1, $arr, true), $collection->contains(1, true));
    }

    public function testChunk()
    {
        $collection = new Collection($arr = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        /** @var Collection[] $chunked */
        $chunked = [];
        $collection->chunk(
            2,
            function (Collection $sub) use (&$chunked) {
                $chunked[] = $sub;
            }
        );

        $this->assertCount(2, $chunked);
        $this->assertCount(2, $chunked[0]);
        $this->assertCount(1, $chunked[1]);
        $this->assertEquals(['key3' => 'value3'], $chunked[1]->getArrayCopy());
        $this->assertEquals($arr, array_merge($chunked[0]->getArrayCopy(), $chunked[1]->getArrayCopy()));

        $result = $collection->chunk(2);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertInstanceOf(Collection::class, $result[0]);
        $this->assertInstanceOf(Collection::class, $result[1]);
        $this->assertCount(2, $result[0]);
        $this->assertCount(1, $result[1]);
    }

    public function testKeys()
    {
        $collection = new Collection(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        $this->assertEquals(['key1', 'key2', 'key3'], $collection->keys()->getArrayCopy());
    }

    public function testValues()
    {
        $collection = new Collection(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        $this->assertEquals(['value1', 'value2', 'value3'], $collection->values()->getArrayCopy());
    }

    public function testUnique()
    {
        $collection = new Collection(['key1' => 'value', 'key2' => 'value2', 'key3' => 'value']);

        $this->assertEquals(['key1' => 'value', 'key2' => 'value2'], $collection->unique()->getArrayCopy());
    }

    public function testFlip()
    {
        $collection = new Collection($arr = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        $this->assertEquals(array_flip($arr), $collection->flip()->getArrayCopy());
    }

    public function testColumn()
    {
        $collection = new Collection(
            $arr = [
                'key1' => ['foo' => 'value1foo', 'bar' => 'value1bar'],
                'key2' => ['foo' => 'value2foo', 'bar' => 'value2bar'],
                'key3' => ['foo' => 'value3foo', 'bar' => 'value3bar']
            ]
        );

        $this->assertEquals(array_column($arr, 'foo'), $collection->column('foo')->getArrayCopy());
        $this->assertEquals(array_column($arr, 'foo', 'bar'), $collection->column('foo', 'bar')->getArrayCopy());
        $this->assertEquals(array_column($arr, null, 'foo'), $collection->column(null, 'foo')->getArrayCopy());
    }

    public function testRand()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $result = $collection->rand();
        $this->assertCount(1, $result);
        $this->assertCount(
            0,
            array_diff(
                $result->getArrayCopy(),
                $collection->getArrayCopy(),
            )
        );

        $result = $collection->rand(3);
        $this->assertCount(3, $result);
        $this->assertCount(
            0,
            array_diff(
                $result->getArrayCopy(),
                $collection->getArrayCopy(),
            )
        );
    }

    public function testSum()
    {
        $collection = new Collection($arr = [1, 2, 3, 4, 5]);

        $this->assertSame(array_sum($arr), $collection->sum());
    }

    public function testAvg()
    {
        $collection = new Collection($arr = [1, 2, 3, 4, 5]);

        $this->assertEquals(3, $collection->avg());
        $this->assertEquals(array_sum($arr) / count($arr), $collection->avg());
    }

    public function testReduce()
    {
        $collection = new Collection($arr = [1, 2, 3, 4, 5]);

        $this->assertEquals(15, $collection->reduce(fn($carry, $item) => $carry + $item));
        $this->assertEquals(1200, $collection->reduce(fn($carry, $item) => $carry * $item, 10));
        $this->assertEquals(
            'Empty collection',
            (new Collection())->reduce(fn($carry, $item) => $carry * $item, 'Empty collection')
        );
    }
}
