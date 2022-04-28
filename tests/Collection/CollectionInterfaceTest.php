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
    public function testNew(string $class)
    {
        /** @var class-string<CollectionInterface> $class */
        $collection = $class::new($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf($class, $collection);
        $this->assertSame($arr, $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGetArrayCopy(string $class)
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGetArrayCopy_recursive(string $class)
    {
        $collection = new $class([
            new $class($arr1 = ['foo', 'bar', 'baz']),
            new $class($arr2 = ['qux', 'quxx'])
        ]);

        $this->assertSame([$arr1, $arr2], $collection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testIsEmpty(string $class)
    {
        $this->assertFalse((new $class(['foo', 'bar', 'baz']))->isEmpty());
        $this->assertTrue((new $class())->isEmpty());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testDebugInfo(string $class)
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->__debugInfo());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testJsonSerialize(string $class)
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame($arr, $collection->jsonSerialize());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testCollect(string $class)
    {
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);
        $newCollection = $collection->collect();

        $this->assertNotSame($collection, $newCollection);
        $this->assertEquals($arr, $newCollection->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSort(string $class)
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
    public function testFilter(string $class)
    {
        $collection = new $class(['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(
            ['foo', 3 => 'qux'],
            $collection->filter(fn($value) => in_array($value, ['foo', 'qux']))->getArrayCopy()
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
    public function testFilterInstanceOf(string $class)
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
    public function testMap(string $class)
    {
        $callback = fn($value) => $value . 'Mapped';
        $collection = new $class($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertSame(array_map($callback, $arr), $collection->map($callback)->getArrayCopy());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testGet(string $class)
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
    public function testFirst(string $class)
    {
        $arr = ['foo', 'bar', 'baz', 'qux', 'quxx'];
        $callback = fn($value) => str_starts_with($value, 'ba');

        $this->assertEquals('bar', (new $class($arr))->search($callback));
        $this->assertEquals('bar', (new $class($arr))->first($callback));
        $this->assertEquals('foo', (new $class($arr))->first());

        $this->assertNull((new $class())->first($callback));
        $this->assertNull((new $class())->first());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testLast(string $class)
    {
        $arr = ['foo', 'bar', 'baz', 'qux', 'quxx'];
        $callback = fn($value) => str_starts_with($value, 'ba');

        $this->assertEquals('baz', (new $class($arr))->last($callback));
        $this->assertEquals('quxx', (new $class($arr))->last());

        $this->assertNull((new $class())->last($callback));
        $this->assertNull((new $class())->last());
    }

    /**
     * @dataProvider collectionTypeProvider
     */
    public function testSlice(string $class)
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
    public function testContains(string $class)
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
    public function testChunk(string $class)
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
    public function testKeys(string $class)
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
    public function testValues(string $class)
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
    public function testUnique(string $class)
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
    public function testFlip(string $class)
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
    public function testColumn(string $class)
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
    public function testRand(string $class)
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
    public function testSum(string $class)
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
    public function testAvg(string $class)
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
    public function testReduce(string $class)
    {
        $arr = [1, 2, 3, 4, 5];

        $this->assertEquals(
            15,
            (new $class($arr))->reduce(fn($carry, $item) => $carry + $item)
        );
        $this->assertEquals(
            1200,
            (new $class($arr))->reduce(fn($carry, $item) => $carry * $item, 10)
        );
        $this->assertEquals(
            'Empty collection',
            (new $class())->reduce(fn($carry, $item) => $carry * $item, 'Empty collection')
        );
    }
}