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

declare(strict_types=1);

namespace Hector\Collection;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Traversable;

class Collection implements CollectionInterface, ArrayAccess
{
    private array $items;

    public function __construct(iterable|Closure $iterable = [])
    {
        $this->items = $this->initList($iterable);
    }

    /**
     * @inheritDoc
     */
    public static function new(iterable|Closure $iterable): static
    {
        return new static($iterable);
    }

    /**
     * New lazy collection.
     *
     * @param Closure|iterable $iterable
     *
     * @return CollectionInterface
     */
    protected function newLazy(Closure|iterable $iterable): CollectionInterface
    {
        return new LazyCollection($iterable);
    }

    /**
     * New lazy collection from current collection.
     *
     * @return CollectionInterface
     */
    public function lazy(): CollectionInterface
    {
        return $this->newLazy($this);
    }

    /**
     * Init list from iterable.
     *
     * @param iterable $iterable
     *
     * @return array
     */
    protected function initList(iterable $iterable = []): array
    {
        if ($iterable instanceof Traversable) {
            return iterator_to_array($iterable);
        }

        return $iterable;
    }

    /////////////////
    /// Countable ///
    /////////////////

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->items);
    }

    ///////////////////////////
    /// CollectionInterface ///
    ///////////////////////////

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return array_map(
            function ($value) {
                if ($value instanceof CollectionInterface) {
                    return $value->getArrayCopy();
                }

                return $value;
            },
            $this->items
        );
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @inheritDoc
     */
    public function collect(): static
    {
        return new static($this->all());
    }

    /**
     * PHP magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->all();
    }

    /**
     * @inheritDoc
     */
    public function sort(int|callable|null $callback = null): static
    {
        $items = $this->items;

        if (is_callable($callback)) {
            uasort($items, $callback);
        } else {
            asort($items, $callback ?? SORT_REGULAR);
        }

        return new static($items);
    }

    /**
     * @inheritDoc
     */
    public function multiSort(callable ...$callback): static
    {
        $items = $this->items;
        uasort($items, fn($a, $b) => $this->multiSortByCmp($a, $b, ...$callback));

        return new static($items);
    }

    protected function multiSortByCmp(mixed $a, mixed $b, callable ...$callback): int
    {
        $current = array_shift($callback);

        if (0 === ($result = $current($a, $b))) {
            if (!empty($callback)) {
                return $this->multiSortByCmp($a, $b, ...$callback);
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function filter(?callable $callback = null): static
    {
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @inheritDoc
     */
    public function filterInstanceOf(string|object ...$class): static
    {
        return $this->filter(function ($value) use (&$class) {
            foreach ($class as $className) {
                if (is_object($className)) {
                    $className = $className::class;
                }

                if ($value instanceof $className) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * @inheritDoc
     */
    public function map(callable $callback): static
    {
        $keys = array_keys($this->items);
        $result = array_map($callback, $this->items, $keys);

        return new static(array_combine($keys, $result));
    }

    /**
     * @inheritDoc
     */
    public function each(callable $callback): static
    {
        $keys = array_keys($this->items);
        array_map($callback, $this->items, $keys);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function search(callable $callback): mixed
    {
        return $this->first($callback);
    }

    /**
     * @inheritDoc
     */
    public function get(int $index = 0): mixed
    {
        return $this->slice($index, 1)->first();
    }

    /**
     * @inheritDoc
     */
    public function first(?callable $callback = null): mixed
    {
        if (null === $callback) {
            if (false !== ($result = reset($this->items))) {
                return $result;
            }

            return null;
        }

        foreach ($this as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function last(?callable $callback = null): mixed
    {
        if (null === $callback) {
            if (false !== ($result = end($this->items))) {
                return $result;
            }

            return null;
        }

        $result = null;

        foreach ($this as $key => $value) {
            if ($callback($value, $key)) {
                $result = $value;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * @inheritDoc
     */
    public function contains(mixed $value, bool $strict = false): bool
    {
        return in_array($value, $this->items, $strict);
    }

    /**
     * @inheritDoc
     */
    public function chunk(int $length, ?callable $callback = null): static
    {
        $collection = new static();

        foreach (array_chunk($this->items, $length, true) as $chunk) {
            $collection->append(new static($chunk));
        }

        if (null !== $callback) {
            return $collection->map($callback);
        }

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    /**
     * @inheritDoc
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * @inheritDoc
     */
    public function unique(): static
    {
        return new static(array_unique($this->items));
    }

    /**
     * @inheritDoc
     */
    public function flip(): static
    {
        return new static(array_flip($this->items));
    }

    /**
     * @inheritDoc
     */
    public function column(string|int|Closure|null $column_key, string|int|Closure|null $index_key = null): static
    {
        return new static(b_array_column($this->items, $column_key, $index_key));
    }

    /**
     * @inheritDoc
     */
    public function rand(int $length = 1): static
    {
        $keys = (array)array_rand($this->items, $length);

        return new static(array_map(fn($key) => $this->items[$key], $keys));
    }

    /**
     * @inheritDoc
     */
    public function sum(): float|int
    {
        return array_sum($this->items);
    }

    /**
     * @inheritDoc
     */
    public function avg(): float|int
    {
        $count = count($this->items);

        if (0 === $count) {
            return 0;
        }

        return array_sum($this->items) / $count;
    }

    /**
     * @inheritDoc
     */
    public function median(): float|int
    {
        $count = count($this->items);

        if (0 === $count) {
            return 0;
        }

        $items = $this->values()->sort()->all();
        $middleIndex = $count / 2;

        if (is_float($middleIndex)) {
            return $items[(int)$middleIndex];
        }

        return ($items[$middleIndex] + $items[$middleIndex - 1]) / 2;
    }

    /**
     * @inheritDoc
     */
    public function variance(): float|int
    {
        if ($this->isEmpty()) {
            return .0;
        }

        $items = $this->values()->sort()->all();
        $count = $this->count();
        $avg = $this->avg();
        $variance = .0;

        foreach ($items as $value) {
            $variance += pow(($value - $avg), 2);
        }

        return $variance / $count;
    }

    /**
     * @inheritDoc
     */
    public function deviation(): float|int
    {
        $count = $this->count();

        if (0 === $count) {
            return .0;
        }

        return pow($this->variance(), .5);
    }

    /**
     * @inheritDoc
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    ///////////////////
    /// ArrayAccess ///
    ///////////////////

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            $this->items[] = $value;
            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Append value(s) to collection.
     *
     * @param mixed ...$value
     *
     * @return static
     */
    public function append(mixed ...$value): static
    {
        array_push($this->items, ...$value);

        return $this;
    }

    /**
     * Prepend value(s) to collection.
     *
     * @param mixed ...$value
     *
     * @return static
     */
    public function prepend(mixed ...$value): static
    {
        array_unshift($this->items, ...$value);

        return $this;
    }
}