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
use Closure;
use Generator;
use Hector\Collection\Collection;
use Hector\Collection\LazyCollection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

class LazyCollectionTest extends TestCase
{
    public function constructProvider(): array
    {
        return [
            [
                ['foo', 'bar'],
            ],
            [
                new ArrayIterator(['foo', 'bar']),
            ],
            [
                fn(): Generator => yield from (['foo', 'bar']),
            ],
            [
                (fn(): Generator => yield from (['foo', 'bar']))(),
            ],
            [
                new LazyCollection(['foo', 'bar']),
            ],
            [
                new Collection(['foo', 'bar']),
            ],
        ];
    }

    /**
     * @dataProvider constructProvider
     */
    public function testConstruct(Closure|iterable $iterable)
    {
        $collection = new LazyCollection($iterable);

        $this->assertInstanceOf(LazyCollection::class, $collection);
        $this->assertEquals(['foo', 'bar'], $collection->getArrayCopy());
    }

    public function testConstruct_notIterable()
    {
        $this->expectException(InvalidArgumentException::class);
        new LazyCollection(fn() => new stdClass());
    }

    public function testCount()
    {
        $collection = new LazyCollection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);
        $length = 0;
        $collection = $collection->count($length);

        $this->assertEquals(count($arr), $length);
        $this->assertEquals($arr, $collection->getArrayCopy());
    }

    public function testGetIterator()
    {
        $collection = new LazyCollection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf(Generator::class, $collection->getIterator());
        $this->assertSame($arr, iterator_to_array($collection->getIterator()));
    }
}
