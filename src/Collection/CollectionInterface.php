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
use Countable;
use IteratorAggregate;
use JsonSerializable;

interface CollectionInterface extends IteratorAggregate, JsonSerializable, Countable
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
     * Get an array representation.
     *
     * @return array
     */
    public function getArrayCopy(): array;

    /**
     * Get values as array.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Is list?
     *
     * @return bool
     */
    public function isList(): bool;

    /**
     * Collect all data from current collection into another one.
     *
     * @return self
     */
    public function collect(): self;

    /**
     * Sort collection with callback.
     *
     * @param int|callable|null $callback
     *
     * @return static
     */
    public function sort(int|callable|null $callback = null): static;

    /**
     * Multiple sort.
     *
     * @param callable ...$callback
     *
     * @return static
     */
    public function multiSort(callable ...$callback): static;

    /**
     * Filter items with function.
     *
     * @param callable|null $callback
     *
     * @return static
     * @see array_filter()
     */
    public function filter(?callable $callback = null): static;

    /**
     * Filter items with object instance comparison.
     *
     * @param string|object ...$class
     *
     * @return static
     * @see array_filter()
     */
    public function filterInstanceOf(string|object ...$class): static;

    /**
     * Apply callback on items and return result of callback.
     *
     * @param callable $callback
     *
     * @return static
     * @see array_map()
     */
    public function map(callable $callback): static;

    /**
     * Apply callback on items and return items.
     *
     * @param callable $callback
     *
     * @return static
     * @see array_walk()
     */
    public function each(callable $callback): static;

    /**
     * Search key of item with callback or value.
     *
     * @param callable|mixed $needle
     * @param bool $strict
     *
     * @return int|string|false
     */
    public function search(mixed $needle, bool $strict = false): int|string|false;

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
     * @return static
     */
    public function slice(int $offset, int|null $length = null): static;

    /**
     * Chunk collection items into collection of fixed length.
     *
     * Callback is applied on each chunk collection.
     *
     * @param int $length
     *
     * @return static
     * @see array_chunk()
     */
    public function chunk(int $length): static;

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
     * @return static
     * @see array_keys()
     */
    public function keys(): static;

    /**
     * Get values of collection.
     *
     * @return static
     * @see array_values()
     */
    public function values(): static;

    /**
     * Get uniques items of collection.
     *
     * @return static
     * @see array_unique()
     */
    public function unique(): static;

    /**
     * Flip keys and values.
     *
     * @return static
     * @see array_flip()
     */
    public function flip(): static;

    /**
     * Reverse order of items.
     *
     * @param bool $preserve_keys If set to true numeric keys are preserved. Non-numeric keys are not affected by this setting and will always be preserved.
     *
     * @return static
     * @see array_reverse()
     */
    public function reverse(bool $preserve_keys = false): static;

    /**
     * Get column value or reindex collection.
     *
     * @param string|int|Closure|null $column_key
     * @param string|int|Closure|null $index_key
     *
     * @return static
     * @see array_column()
     * @see b_array_column()
     */
    public function column(string|int|Closure|null $column_key, string|int|Closure|null $index_key = null): static;

    /**
     * Get random values of collection.
     *
     * @param int $length
     *
     * @return static
     * @see array_rand()
     */
    public function rand(int $length = 1): static;

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

    /**
     * Join collection items as a string.
     *
     * @param string $glue
     * @param string|null $finalGlue
     *
     * @return string
     * @see implode()
     */
    public function join(string $glue = '', ?string $finalGlue = null): string;

    /**
     * Group items by a key or callback result.
     *
     * @param string|int|Closure $groupBy
     *
     * @return static
     */
    public function groupBy(string|int|Closure $groupBy): static;
}
