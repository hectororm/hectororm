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
use IteratorAggregate;
use JsonSerializable;

interface CollectionInterface extends IteratorAggregate, JsonSerializable
{
    /**
     * Init new collection.
     *
     * @param Closure|iterable $iterable $iterable
     *
     * @return static
     */
    public static function new(Closure|iterable $iterable): static;

    /**
     * PHP magic method.
     *
     * MUST return array representation of collection.
     *
     * @return array
     * @see CollectionInterface::getArrayCopy()
     */
    public function __debugInfo(): array;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array;

    /**
     * Get an array representation.
     *
     * @return array
     */
    public function getArrayCopy(): array;

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Collect data in new collection.
     *
     * @return self
     */
    public function collect(): self;

    /**
     * Sort collection with callback.
     *
     * @param int|callable|null $callback
     *
     * @return self
     */
    public function sort(int|callable|null $callback = null): self;

    /**
     * Filter items with function.
     *
     * @param callable|null $callback
     *
     * @return self
     * @see array_filter()
     */
    public function filter(?callable $callback = null): self;

    /**
     * Filter items with object instance comparison.
     *
     * @param string|object ...$class
     *
     * @return self
     * @see array_filter()
     */
    public function filterInstanceOf(string|object ...$class): self;

    /**
     * Apply callback on items.
     *
     * @param callable $callback
     *
     * @return self
     * @see array_map()
     */
    public function map(callable $callback): self;

    /**
     * Search item with callback.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function search(callable $callback): mixed;

    /**
     * Get item at index.
     *
     * @param int $index
     *
     * @return mixed
     * @see array_slice()
     */
    public function get(int $index = 0): mixed;

    /**
     * Search first item.
     *
     * If callback given, search first item whose respect callback.
     *
     * @param callable|null $callback
     *
     * @return mixed
     * @see reset()
     */
    public function first(?callable $callback = null): mixed;

    /**
     * Search last item with callback.
     *
     * If callback given, search last item whose respect callback.
     *
     * @param callable|null $callback
     *
     * @return mixed
     * @see end()
     */
    public function last(?callable $callback = null): mixed;

    /**
     * Extract a slice of the collection.
     *
     * @param int $offset
     * @param int|null $length
     *
     * @return self
     */
    public function slice(int $offset, int|null $length = null): self;

    /**
     * Chunk collection items into collection of fixed length.
     *
     * Callback is applied on each chunk collection.
     *
     * @param int $length
     *
     * @return self
     * @see array_chunk()
     */
    public function chunk(int $length): self;

    /**
     * Collection contains value?
     *
     * @param mixed $value
     * @param bool $strict
     *
     * @return bool
     * @see in_array()
     */
    public function contains(mixed $value, bool $strict = false): bool;

    /**
     * Get keys of collection items.
     *
     * @return self
     * @see array_keys()
     */
    public function keys(): self;

    /**
     * Get values of collection.
     *
     * @return self
     * @see array_values()
     */
    public function values(): self;

    /**
     * Get unique items of collection.
     *
     * @return self
     * @see array_unique()
     */
    public function unique(): self;

    /**
     * Flip keys and values.
     *
     * @return self
     * @see array_flip()
     */
    public function flip(): self;

    /**
     * Get column value or reindex collection.
     *
     * @param string|int|Closure|null $column_key
     * @param string|int|Closure|null $index_key
     *
     * @return self
     * @see array_column()
     * @see b_array_column()
     */
    public function column(string|int|Closure|null $column_key, string|int|Closure|null $index_key = null): self;

    /**
     * Get random values of collection.
     *
     * @param int $length
     *
     * @return self
     * @see array_rand()
     */
    public function rand(int $length = 1): self;

    /**
     * Get sum of values.
     *
     * @return float|int
     * @see array_sum()
     */
    public function sum(): float|int;

    /**
     * Get average of values.
     *
     * @return float|int
     */
    public function avg(): float|int;

    /**
     * Get median of values.
     *
     * @return float|int
     */
    public function median(): float|int;

    /**
     * Get population variance of values.
     *
     * @return float|int
     */
    public function variance(): float|int;

    /**
     * Get population deviation of values.
     *
     * @return float|int
     */
    public function deviation(): float|int;

    /**
     * Reduce collection.
     *
     * @param callable $callback
     * @param mixed|null $initial
     *
     * @return mixed
     * @see array_reduce()
     */
    public function reduce(callable $callback, mixed $initial = null): mixed;
}