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

use Hector\Collection\Collection;
use Hector\Collection\LazyCollection;
use Iterator;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testLazy()
    {
        $collection = new Collection(['foo', 'bar', 'baz', 'qux', 'quxx']);
        $collection2 = $collection->lazy();

        $this->assertInstanceOf(LazyCollection::class, $collection2);
        $this->assertEquals($collection->getArrayCopy(), $collection2->getArrayCopy());
    }

    public function testCount()
    {
        $collection = new Collection($arr = ['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertCount(count($arr), $collection);

        $collection = new Collection();

        $this->assertCount(0, $collection);
    }

    public function testGetIterator()
    {
        $collection = new Collection(['foo', 'bar', 'baz', 'qux', 'quxx']);

        $this->assertInstanceOf(Iterator::class, $collection->getIterator());
        $this->assertSame($collection->getArrayCopy(), iterator_to_array($collection->getIterator()));
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
}
