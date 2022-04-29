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

use Closure;
use Exception;
use Generator;
use InvalidArgumentException;

class LazyCollection implements CollectionInterface
{
    protected Generator $items;

    /**
     * LazyCollection constructor.
     *
     * @param Closure|iterable $iterable
     */
    public function __construct(Closure|iterable $iterable = [])
    {
        try {
            $this->items = $this->initList($iterable);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new InvalidArgumentException('First argument must be iterable', previous: $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public static function new(iterable|Closure $iterable): static
    {
        return new static($iterable);
    }

    /**
     * New default collection.
     *
     * @param Closure|iterable $iterable
     *
     * @return CollectionInterface
     */
    protected function newDefault(Closure|iterable $iterable): CollectionInterface
    {
        return new Collection($iterable);
    }

    /**
     * Init list from iterable.
     *
     * @param Closure|iterable $iterable
     *
     * @return Generator
     * @throws Exception
     */
    protected function initList(Closure|iterable $iterable): Generator
    {
        if ($iterable instanceof CollectionInterface) {
            $iterable = $iterable->getIterator();
        }

        if (!$iterable instanceof Generator) {
            if (is_iterable($iterable)) {
                $iterable = fn(): Generator => yield from $iterable;
            }
        }

        if ($iterable instanceof Closure) {
            $iterable = $iterable();
        }

        if (!$iterable instanceof Generator) {
            throw new InvalidArgumentException('Argument must be iterable');
        }

        return $iterable;
    }

    /**
     * Count number of items and return new collection.
     *
     * @param int $length
     *
     * @return CollectionInterface
     */
    public function count(int &$length = 0): CollectionInterface
    {
        $collection = $this->collect();
        $length = $collection->count();

        return $collection;
    }

    ///////////////////////////
    /// CollectionInterface ///
    ///////////////////////////

    /**
     * @inheritDoc
     */
    public function getIterator(): Generator
    {
        return $this->items;
    }

    /**
     * PHP magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        $arr = $this->getArrayCopy();
        $this->items = (fn(): Generator => yield from $arr)();

        return $arr;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
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
            iterator_to_array($this->items)
        );
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return !$this->items->valid();
    }

    /**
     * @inheritDoc
     */
    public function collect(): CollectionInterface
    {
        return $this->newDefault($this->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function sort(callable|int|null $callback = null): static
    {
        $collection = $this->newDefault($this->items);

        return new static($collection->sort($callback));
    }

    /**
     * @inheritDoc
     */
    public function filter(?callable $callback = null): static
    {
        $generator = function ($callback): Generator {
            foreach ($this->items as $key => $item) {
                if (null === $callback) {
                    if (!empty($item)) {
                        yield $key => $item;
                    }
                    continue;
                }
                if ($callback($item, $key)) {
                    yield $key => $item;
                }
            }
        };

        return new static($generator($callback));
    }

    /**
     * @inheritDoc
     */
    public function filterInstanceOf(string|object ...$class): static
    {
        return $this->filter(function ($item) use (&$class) {
            foreach ($class as $className) {
                if (is_object($className)) {
                    $className = $className::class;
                }

                if ($item instanceof $className) {
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
        $generator = function ($callback): Generator {
            foreach ($this->items as $key => $item) {
                yield $key => $callback($item, $key);
            }
        };

        return new static($generator($callback));
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
            return $this->items->current();
        }

        foreach ($this as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function last(?callable $callback = null): mixed
    {
        $result = null;

        foreach ($this as $key => $item) {
            if (null === $callback || $callback($item, $key)) {
                $result = $item;
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function slice(int $offset, ?int $length = null): CollectionInterface
    {
        $generator = function ($offset, $length): Generator {
            $stack = [];

            // Normalize
            if ($offset < 0 && $length < 0) {
                $length = abs($offset) + $length;
            }

            // Length 0
            if (null !== $length && $offset < 0 && $length <= 0) {
                return new static([]);
            }

            $i = 0;
            foreach ($this as $key => $value) {
                if ($offset >= 0 && $offset > $i) {
                    $i++;
                    continue;
                }

                $stack[$key] = $value;
                $i++;

                if (null !== $length && $length > 0 && (count($stack) > ($length - min($offset, 0)))) {
                    if ($offset < 0) {
                        array_shift($stack);
                        continue;
                    }
                    array_pop($stack);
                }
            }

            yield from array_slice($stack, min(0, $offset), $length, true);
        };

        return new static($generator($offset, $length));
    }

    /**
     * @inheritDoc
     */
    public function contains(mixed $value, bool $strict = false): bool
    {
        foreach ($this as $item) {
            if (true === $strict && $item === $value) {
                return true;
            }

            if (false === $strict && $item == $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function chunk(int $length): static
    {
        $generator = function ($length): Generator {
            while ($this->items->valid()) {
                $arr = [];
                $i = 0;

                do {
                    $i++;
                    $arr[$this->items->key()] = $this->items->current();
                    $this->items->next();
                } while ($this->items->valid() && $i < $length);

                yield $this->newDefault($arr);
            }
        };

        return new static($generator($length));
    }

    /**
     * @inheritDoc
     */
    public function keys(): static
    {
        $generator = function (): Generator {
            foreach ($this->items as $key => $item) {
                yield $key;
            }
        };

        return new static($generator());
    }

    /**
     * @inheritDoc
     */
    public function values(): static
    {
        $generator = function (): Generator {
            foreach ($this->items as $item) {
                yield $item;
            }
        };

        return new static($generator());
    }

    /**
     * @inheritDoc
     */
    public function unique(): static
    {
        $generator = function (): Generator {
            $hashes = [];

            foreach ($this->items as $key => $item) {
                $hash = md5((string)$item);

                if (in_array($hash, $hashes)) {
                    continue;
                }
                $hashes[] = $hash;

                yield $key => $item;
            }
        };

        return new static($generator());
    }

    /**
     * @inheritDoc
     */
    public function flip(): static
    {
        $generator = function (): Generator {
            foreach ($this->items as $key => $item) {
                yield $item => $key;
            }
        };

        return new static($generator());
    }

    /**
     * @inheritDoc
     */
    public function column(int|Closure|string|null $column_key, int|Closure|string|null $index_key = null): static
    {
        $generator = function ($column_key, $index_key): Generator {
            foreach ($this->items as $item) {
                $result = b_array_column([$item], $column_key, $index_key);

                foreach ($result as $key2 => $value2) {
                    if (null === $index_key) {
                        yield $value2;
                        break;
                    }

                    yield $key2 => $value2;
                }
            }
        };

        return new static($generator($column_key, $index_key));
    }

    /**
     * @inheritDoc
     */
    public function rand(int $length = 1): static
    {
        return new static($this->collect()->rand($length));
    }

    /**
     * @inheritDoc
     */
    public function sum(): float|int
    {
        return $this->collect()->sum();
    }

    /**
     * @inheritDoc
     */
    public function avg(): float|int
    {
        return $this->collect()->avg();
    }

    /**
     * @inheritDoc
     */
    public function median(): float|int
    {
        return $this->collect()->median();
    }

    /**
     * @inheritDoc
     */
    public function variance(): float|int
    {
        return $this->collect()->variance();
    }

    /**
     * @inheritDoc
     */
    public function deviation(): float|int
    {
        return $this->collect()->deviation();
    }

    /**
     * @inheritDoc
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return $this->collect()->reduce($callback, $initial);
    }
}