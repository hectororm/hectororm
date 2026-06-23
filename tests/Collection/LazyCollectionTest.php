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
use LogicException;
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
    public function testConstruct(Closure|iterable $iterable): void
    {
        $collection = new LazyCollection($iterable);

        $this->assertInstanceOf(LazyCollection::class, $collection);
        $this->assertEquals(['foo', 'bar'], $collection->getArrayCopy());
    }

    public function testConstruct_notIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LazyCollection(fn(): stdClass => new stdClass());
    }

    public function testGetIterator(): void
    {
        $collection = new LazyCollection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf(Generator::class, $collection->getIterator());
        $this->assertSame($arr, iterator_to_array($collection->getIterator()));
    }

    public function testFlipSkipsNonScalarItems(): void
    {
        // Non-scalar items used to raise a fatal TypeError when yielded as keys;
        // they are now skipped, mirroring array_flip()'s "entry skipped" behaviour.
        $collection = new LazyCollection(['a' => 'x', 'b' => new stdClass(), 'c' => ['nested'], 'd' => 'z']);

        $this->assertSame(['x' => 'a', 'z' => 'd'], $collection->flip()->getArrayCopy());
    }

    public function testSliceWithPositiveWindowDoesNotDrainTheSource(): void
    {
        // A virtually unbounded source: it records how many items get pulled and trips
        // if it is fully drained, so a regression to the old "drain everything" behaviour
        // fails fast (and would hang on a truly infinite generator).
        $pulled = 0;
        $source = function () use (&$pulled): Generator {
            for ($i = 0; ; $i++) {
                if ($pulled++ > 1000) {
                    throw new LogicException('Source was drained: slice() is not lazy.');
                }

                yield $i;
            }
        };

        $this->assertSame([0], (new LazyCollection($source))->slice(0, 1)->getArrayCopy());
        // Window of length 1 from offset 0: only the window plus one overshoot is pulled.
        $this->assertLessThanOrEqual(2, $pulled);
    }

    public function testGetDoesNotDrainTheSource(): void
    {
        $pulled = 0;
        $source = function () use (&$pulled): Generator {
            for ($i = 0; ; $i++) {
                if ($pulled++ > 1000) {
                    throw new LogicException('Source was drained: get() is not lazy.');
                }

                yield $i;
            }
        };

        $this->assertSame(2, (new LazyCollection($source))->get(2));
        // get(2) == slice(2, 1): items 0..2 plus one overshoot.
        $this->assertLessThanOrEqual(4, $pulled);
    }
}
