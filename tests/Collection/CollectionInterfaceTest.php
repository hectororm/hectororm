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

use DateTime;
use Hector\Collection\Collection;
use Hector\Collection\CollectionInterface;
use Hector\Collection\LazyCollection;
use PHPUnit\Framework\TestCase;
use stdClass;

class CollectionInterfaceTest extends TestCase
{
    public function collectionTypeProvider(): array
    {
        return [
            [Collection::class],
            [LazyCollection::class],
        ];
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testNew(string $class): void
    {
        /** @var class-string<CollectionInterface> $class */
        $collection = $class::new($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf($class, $collection);
        $this->assertSame($arr, $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGetArrayCopy(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGetArrayCopy_recursive(string $class): void
    {
        $collection = new $class(
            $arr = [
                new $class($arr1 = ['foo', 'bar', 'baz']),
                new $class($arr2 = ['qux', 'quxx'])
            ]
        );

        $result = $collection->getArrayCopy();
        $this->assertNotSame($arr, $result);
        $this->assertSame([$arr1, $arr2], $result);
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testAll(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->all());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testAll_recursive(string $class): void
    {
        $collection = new $class(
            $arr = [
                new $class(['foo', 'bar', 'baz']),
                new $class(['qux', 'quxx'])
            ]
        );

        $this->assertSame($arr, $collection->all());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testCount(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertCount(count($arr), $collection);
        $this->assertSame($arr, $collection->all());

        $collection = new $class();

        $this->assertCount(0, $collection);
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testIsEmpty(string $class): void
    {
        $this->assertFalse((new $class(['foo', 'bar', 'baz']))->isEmpty());
        $this->assertTrue((new $class())->isEmpty());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testIsList(string $class): void
    {
        $this->assertFalse((new $class(['foo', 2 => 'bar', 'baz']))->isList());
        $this->assertTrue((new $class(['foo', 'bar', 'baz']))->isList());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testDebugInfo(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->__debugInfo());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testJsonSerialize(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->jsonSerialize());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testCollect(string $class): void
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);
        $newCollection = $collection->collect();

        $this->assertNotSame($collection, $newCollection);
        $this->assertEquals($arr, $newCollection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testCollect_multiDimensional(string $class): void
    {
        $collection = new $class($arr = [['foo', 'bar'], new $class(['baz', 'qux']), ['quxx']]);
        $newCollection = $collection->collect();

        $this->assertNotSame($collection, $newCollection);
        $this->assertEquals($arr, $newCollection->all());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSort(string $class): void
    {
        $arr = ['d' => 'Lemon', 'a' => 'orange', 'b1' => 'banana10', 'b2' => 'banana2', 'c' => 'apple'];

        $this->assertSame(
            ['d' => 'Lemon', 'c' => 'apple', 'b1' => 'banana10', 'b2' => 'banana2', 'a' => 'orange'],
            (new $class($arr))->sort()->getArrayCopy()
        );
        $this->assertSame(
            ['d' => 'Lemon', 'c' => 'apple', 'b2' => 'banana2', 'b1' => 'banana10', 'a' => 'orange'],
            (new $class($arr))->sort(SORT_NATURAL)->getArrayCopy()
        );
        $this->assertSame(
            ['c' => 'apple', 'b1' => 'banana10', 'b2' => 'banana2', 'd' => 'Lemon', 'a' => 'orange'],
            (new $class($arr))->sort('strcasecmp')->getArrayCopy()
        );

        // Not moved initial array
        $this->assertSame(
            ['d' => 'Lemon', 'a' => 'orange', 'b1' => 'banana10', 'b2' => 'banana2', 'c' => 'apple'],
            $arr
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testMultiSort(string $class): void
    {
        $arr = [
            'l' => ['name' => 'Lemon', 'nb' => 1],
            'o' => ['name' => 'Orange', 'nb' => 1],
            'b1' => ['name' => 'Banana', 'nb' => 5],
            'b2' => ['name' => 'Banana', 'nb' => 1],
            'a1' => ['name' => 'Apple', 'nb' => 10],
            'a2' => ['name' => 'Apple', 'nb' => 1],
        ];

        $this->assertSame(
            [
                'a2' => ['name' => 'Apple', 'nb' => 1],
                'a1' => ['name' => 'Apple', 'nb' => 10],
                'b2' => ['name' => 'Banana', 'nb' => 1],
                'b1' => ['name' => 'Banana', 'nb' => 5],
                'l' => ['name' => 'Lemon', 'nb' => 1],
                'o' => ['name' => 'Orange', 'nb' => 1],
            ],
            (new $class($arr))
                ->multiSort(
                    fn($value1, $value2): int => $value1['name'] <=> $value2['name'],
                    fn($value1, $value2): int => $value1['nb'] <=> $value2['nb'],
                )->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testFilter(string $class): void
    {
        $collection = new $class(['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(
            ['foo', 3 => 'qux'],
            $collection->filter(fn($value): bool => in_array($value, ['foo', 'qux']))->getArrayCopy()
        );

        $collection = new $class(['foo', 0, 'baz', false, '']);

        $this->assertSame(
            ['foo', 2 => 'baz'],
            $collection->filter()->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testFilterInstanceOf(string $class): void
    {
        $arr = [
            $value1 = new stdClass(),
            $value2 = new class extends stdClass {
            },
            new $class(),
            $value4 = new DateTime(),
            'string',
        ];

        $this->assertSame(
            [3 => $value4],
            (new $class($arr))->filterInstanceOf(DateTime::class)->getArrayCopy()
        );
        $this->assertSame(
            [$value1, $value2],
            (new $class($arr))->filterInstanceOf(stdClass::class)->getArrayCopy()
        );
        $this->assertSame(
            [$value1, $value2, []],
            (new $class($arr))->filterInstanceOf(stdClass::class, new $class())->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testMap(string $class): void
    {
        $callback = fn($value): string => $value . 'Mapped';
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(array_map($callback, $arr), $collection->map($callback)->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testEach(string $class): void
    {
        $result = 0;
        $callback = fn($value): int => $result++;
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $collection = $collection->each($callback);

        $this->assertEquals($arr, $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSearch(string $class): void
    {
        $arr = ['foo', 'bar', '1', 1, 'quxx'];
        $callback = fn($value): bool => str_starts_with($value, 'ba');

        $this->assertSame(2, (new $class($arr))->search(1));
        $this->assertSame(3, (new $class($arr))->search(1, true));
        $this->assertSame(1, (new $class($arr))->search($callback));
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGet(string $class): void
    {
        $arr = ['foo', 'bar' => 'baz', 'qux', 'quxx'];

        $this->assertEquals('foo', (new $class($arr))->get());
        $this->assertEquals('qux', (new $class($arr))->get(2));
        $this->assertEquals('quxx', (new $class($arr))->get(-1));

        $this->assertEquals('foo', (new $class($arr))->get(-15));
        $this->assertNull((new $class($arr))->get(5));
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testFirst(string $class): void
    {
        $arr = ['foo', 'bar', 'baz', 'qux', 'quxx'];
        $callback = fn($value): bool => str_starts_with($value, 'ba');

        $this->assertEquals('bar', (new $class($arr))->first($callback));
        $this->assertEquals('foo', (new $class($arr))->first());

        $this->assertNull((new $class())->first($callback));
        $this->assertNull((new $class())->first());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testLast(string $class): void
    {
        $arr = ['foo', 'bar', 'baz', 'qux', 'quxx'];
        $callback = fn($value): bool => str_starts_with($value, 'ba');

        $this->assertEquals('baz', (new $class($arr))->last($callback));
        $this->assertEquals('quxx', (new $class($arr))->last());

        $this->assertNull((new $class())->last($callback));
        $this->assertNull((new $class())->last());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSlice(string $class): void
    {
        $arr = array_flip(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);

        $this->assertEquals($arr, (new $class($arr))->slice(0, 8)->getArrayCopy());
        $this->assertEquals($arr, (new $class($arr))->slice(0, 20)->getArrayCopy());

        $this->assertEquals(
            ['e' => 4, 'f' => 5, 'g' => 6],
            (new $class($arr))->slice(-4, 3)->getArrayCopy()
        );
        $this->assertEquals(
            ['c' => 2, 'd' => 3, 'e' => 4, 'f' => 5, 'g' => 6],
            (new $class($arr))->slice(2, -1)->getArrayCopy()
        );

        $this->assertEquals(
            ['a' => 0, 'b' => 1, 'c' => 2],
            (new $class($arr))->slice(0, 3)->getArrayCopy()
        );
        $this->assertEquals(
            ['d' => 3, 'e' => 4, 'f' => 5],
            (new $class($arr))->slice(-5, 3)->getArrayCopy()
        );
        $this->assertEquals(
            ['h' => 7],
            (new $class($arr))->slice(-1, 3)->getArrayCopy()
        );
        $this->assertEquals(
            [],
            (new $class($arr))->slice(-1, -3)->getArrayCopy()
        );
        $this->assertEquals(
            [],
            (new $class($arr))->slice(1, 0)->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testContains(string $class): void
    {
        $arr = ['1', '2', 1, 3, 4];

        $this->assertTrue((new $class($arr))->contains(2, false));
        $this->assertFalse((new $class($arr))->contains(2, true));
        $this->assertTrue((new $class($arr))->contains('1', false));
        $this->assertTrue((new $class($arr))->contains(1, true));

        $this->assertSame(
            in_array(2, $arr, false),
            (new $class($arr))->contains(2, false)
        );
        $this->assertSame(
            in_array(2, $arr, true),
            (new $class($arr))->contains(2, true)
        );
        $this->assertSame(
            in_array(1, $arr, false),
            (new $class($arr))->contains(1, false)
        );
        $this->assertSame(
            in_array(1, $arr, true),
            (new $class($arr))->contains(1, true)
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testChunk(string $class): void
    {
        $arr = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];

        $result = (new $class($arr))->chunk(2);
        $this->assertInstanceOf(CollectionInterface::class, $result);
        $result = iterator_to_array($result);
        $this->assertInstanceOf(CollectionInterface::class, $result[0]);
        $this->assertInstanceOf(CollectionInterface::class, $result[1]);
        $this->assertCount(2, $result[0]->getArrayCopy());
        $this->assertCount(1, $result[1]->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testKeys(string $class): void
    {
        $collection = new $class(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        $this->assertEquals(
            ['key1', 'key2', 'key3'],
            $collection->keys()->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testValues(string $class): void
    {
        $collection = new $class(['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']);

        $this->assertEquals(
            ['value1', 'value2', 'value3'],
            $collection->values()->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testUnique(string $class): void
    {
        $arr = ['key1' => 'value', 'key2' => 'value2', 'key3' => 'value'];

        $this->assertEquals(
            ['key1' => 'value', 'key2' => 'value2'],
            (new $class($arr))->unique()->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testFlip(string $class): void
    {
        $arr = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];

        $this->assertEquals(
            array_flip($arr),
            (new $class($arr))->flip()->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testReverse(string $class): void
    {
        $arr = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];

        $this->assertEquals(
            array_reverse($arr),
            (new $class($arr))->reverse()->getArrayCopy()
        );
        $this->assertEquals(
            array_reverse($arr, true),
            (new $class($arr))->reverse(true)->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testColumn(string $class): void
    {
        $arr = [
            'key1' => ['foo' => 'value1foo', 'bar' => 'value1bar'],
            'key2' => ['foo' => 'value2foo', 'bar' => 'value2bar'],
            'key3' => ['foo' => 'value3foo', 'bar' => 'value3bar']
        ];

        $this->assertEquals(
            array_column($arr, 'foo'),
            (new $class($arr))->column('foo')->getArrayCopy()
        );
        $this->assertEquals(
            array_column($arr, 'foo', 'bar'),
            (new $class($arr))->column('foo', 'bar')->getArrayCopy()
        );
        $this->assertEquals(
            array_column($arr, null, 'foo'),
            (new $class($arr))->column(null, 'foo')->getArrayCopy()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testRand(string $class): void
    {
        $arr = ['foo', 'bar', 'baz', 'qux', 'quxx'];

        $result = (new $class($arr))->rand()->getArrayCopy();
        $this->assertCount(1, $result);
        $this->assertCount(
            0,
            array_diff(
                $result,
                (new $class($arr))->getArrayCopy(),
            )
        );

        $result = (new $class($arr))->rand(3)->getArrayCopy();
        $this->assertCount(3, $result);
        $this->assertCount(
            0,
            array_diff(
                $result,
                (new $class($arr))->getArrayCopy(),
            )
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSum(string $class): void
    {
        $collection = new $class($arr = [1, 2, 3, 4, 5]);

        $this->assertSame(
            array_sum($arr),
            $collection->sum()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testAvg(string $class): void
    {
        $this->assertEquals(0, (new $class())->avg());

        $arr = [1, 2, 3, 4, 5];

        $this->assertEquals(
            3,
            (new $class($arr))->avg()
        );
        $this->assertEquals(
            array_sum($arr) / count($arr),
            (new $class($arr))->avg()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testMedian(string $class): void
    {
        $this->assertEquals(0, (new $class())->median());

        $this->assertEquals(
            3,
            (new $class([1, 3, 5]))->median()
        );
        $this->assertEquals(
            4,
            (new $class([1, 3, 5, 7]))->median()
        );
        $this->assertEquals(
            3,
            (new $class([1, 2, 3, 4, 5]))->median()
        );
        $this->assertEquals(
            2,
            (new $class([1, 1, 2, 2, 3, 5]))->median()
        );
        $this->assertEquals(
            3,
            (new $class([1, 1, 2, 3, 3, 5, 10, 10, 10]))->median()
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testVariance(string $class): void
    {
        $this->assertEqualsWithDelta(0, (new $class())->variance(), .000001);

        $this->assertEqualsWithDelta(
            2.666666666667,
            (new $class([1, 3, 5]))->variance(),
            .000001
        );
        $this->assertEqualsWithDelta(
            5,
            (new $class([1, 3, 5, 7]))->variance(),
            .000001
        );
        $this->assertEqualsWithDelta(
            2,
            (new $class([1, 2, 3, 4, 5]))->variance(),
            .000001
        );
        $this->assertEqualsWithDelta(
            1.888888888889,
            (new $class([1, 1, 2, 2, 3, 5]))->variance(),
            .000001
        );
        $this->assertEqualsWithDelta(
            13.777777777778,
            (new $class([1, 1, 2, 3, 3, 5, 10, 10, 10]))->variance(),
            .000001
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testDeviation(string $class): void
    {
        $this->assertEqualsWithDelta(0, (new $class())->deviation(), .000001);

        $this->assertEqualsWithDelta(
            1.632993161855,
            (new $class([1, 3, 5]))->deviation(),
            .000001
        );
        $this->assertEqualsWithDelta(
            2.2360679775,
            (new $class([1, 3, 5, 7]))->deviation(),
            .000001
        );
        $this->assertEqualsWithDelta(
            1.414213562373,
            (new $class([1, 2, 3, 4, 5]))->deviation(),
            .000001
        );
        $this->assertEqualsWithDelta(
            1.374368541873,
            (new $class([1, 1, 2, 2, 3, 5]))->deviation(),
            .000001
        );
        $this->assertEqualsWithDelta(
            3.711842908553,
            (new $class([1, 1, 2, 3, 3, 5, 10, 10, 10]))->deviation(),
            .000001
        );
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testReduce(string $class): void
    {
        $arr = [1, 2, 3, 4, 5];

        $this->assertEquals(
            15,
            (new $class($arr))->reduce(fn($carry, $item): float|int|array => $carry + $item)
        );
        $this->assertEquals(
            1200,
            (new $class($arr))->reduce(fn($carry, $item): int|float => $carry * $item, 10)
        );
        $this->assertEquals(
            'Empty collection',
            (new $class())->reduce(fn($carry, $item): int|float => $carry * $item, 'Empty collection')
        );
    }
}
