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
use Closure;
use IteratorAggregate;
use JsonSerializable;

interface CollectionInterface extends IteratorAggregate, ArrayAccess, JsonSerializable
{
    /**
     * Get an array representation.
     *
     * @return array
     */
    public function getArrayCopy(): array;

    /**
     * Append value(s) to collection.
     *
     * @param mixed ...$value
     *
     * @return static
     */
    public function append(mixed ...$value): static;

    /**
     * Prepend value(s) to collection.
     *
     * @param mixed ...$value
     *
     * @return static
     */
    public function prepend(mixed ...$value): static;

    /**
     * Sort collection with callback.
     *
     * @param int|callable|null $callback
     *
     * @return static
     */
    public function sort(int|callable|null $callback = null): static;

    /**
     * Filter values with function.
     *
     * @param callable|null $callback
     *
     * @return static
     * @see array_filter()
     */
    public function filter(?callable $callback = null): static;

    /**
     * Filter values with object instance comparison.
     *
     * @param string|object ...$class
     *
     * @return static
     * @see array_filter()
     */
    public function filterInstanceOf(string|object ...$class): static;

    /**
     * Apply function on values.
     *
     * @param callable $callback
     *
     * @return static
     * @see array_map()
     */
    public function map(callable $callback): static;

    /**
     * Search value with callback.
     *
     * @param callable $callback
     *
     * @return mixed
     */
    public function search(callable $callback): mixed;

    /**
     * Search first value with callback.
     *
     * @param callable|null $callback
     *
     * @return mixed
     * @see reset()
     */
    public function first(?callable $callback = null): mixed;

    /**
     * Search last value with callback.
     *
     * @param callable|null $callback
     *
     * @return mixed
     * @see end()
     */
    public function last(?callable $callback = null): mixed;

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
     * Chunk collection items into collection of fixed length.
     *
     * @param int $length
     * @param callable|null $callback
     *
     * @return static
     * @see array_chunk()
     */
    public function chunk(int $length, ?callable $callback = null): static;

    /**
     * Get keys of collection.
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
     * Get unique values of collection.
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