# Hector Collection

[![Latest Version](https://img.shields.io/packagist/v/hectororm/collection.svg?style=flat-square)](https://github.com/hectororm/collection/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/collection/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/collection.svg?style=flat-square)](https://github.com/hectororm/collection/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Collection** is the module of Hector ORM. Can be used independently of ORM.

## Installation

You can install **Hector Collection** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require hectororm/collection
```

## Usage

Collection can be view like an array. But you don't call `array_*()` functions but directly methods of collection.

```php
$collection = new Collection();
$collection = new Collection(['my', 'initial', 'array']);

$collection = new LazyCollection();
$collection = new LazyCollection(['my', 'initial', 'array']);
```

The collections implement `CollectionInterface`, only `Collection` is countable.

Available collections:

- `Collection`: default collection
- `LazyCollection`: lazy collection whose uses generators to improve performances, but is *unique usage*!

### `CollectionInterface` methods

#### `CollectionInterface::new(Closure|iterable $iterable): static`

Create new instance of current collection.

```php
$collection = Collection::new(['foo', 'bar']);
```

#### `Collection::count(): int`

Count number of items in collection.

For lazy collection, all iterator is traversed.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection->count(); // Returns `3`
count($collection); // Returns `3`
```

Similar to `count()` function.

#### `CollectionInterface::getArrayCopy(): array`

Use method to get the array representation of your collection.

```php
$collection = new Collection();
$collection->getArrayCopy(); // Returns `[]`

$collection = new Collection(['my', 'initial', new Collection(['array'])]);
$collection->getArrayCopy(); // Returns `['my', 'initial', ['array']]`
```

#### `CollectionInterface::all(): array`

Use method to get all values of your collection as array.

```php
$collection = new Collection();
$collection->all(); // Returns `[]`

$collection = new Collection(['my', 'initial', new Collection(['array'])]);
$collection->all(); // Returns `['my', 'initial', new Collection(['array'])]`
```

#### `CollectionInterface::isEmpty(): bool`

Know if collection is empty.

```php
Collection::new(['foo', 'bar'])->isEmpty(); // Returns FALSE
Collection::new()->isEmpty(); // Returns TRUE
```

#### `CollectionInterface::isList(): bool`

Know if collection is list.

```php
Collection::new(['foo', 'bar'])->isList(); // Returns TRUE
Collection::new(['foo', 'b' => 'bar'])->isList(); // Returns FALSE
```

#### `CollectionInterface::collect(): self`

Collect all data from current collection into another one.

For lazy collection, collect all remaining items into classic collection.

```php
$collection = Collection::new(['foo', 'bar']);
$newCollection = $collection->collect();
```

#### `CollectionInterface::sort(callable|int|null $callback = null): static`

Sort items of collection.

```php
$collection = Collection::new(['foo', 'bar']);
$collection = $collection->sort();
$collection->getArrayCopy(); // Returns `['bar', 'foo']`
```

Similar to PHP array sort functions.

#### `CollectionInterface::multiSort(callable ...$callback): static`

Multi sort items of collection.

```php
$collection = Collection::new([
    'l' => ['name' => 'Lemon', 'nb' => 1],
    'o' => ['name' => 'Orange', 'nb' => 1],
    'b1' => ['name' => 'Banana', 'nb' => 5],
    'b2' => ['name' => 'Banana', 'nb' => 1],
    'a1' => ['name' => 'Apple', 'nb' => 10],
    'a2' => ['name' => 'Apple', 'nb' => 1],
]);
$collection = $collection->sort(
    fn($item1, $item2) => $item1['name'] <=> $item2['name'],
    fn($item1, $item2) => $item1['nb'] <=> $item2['nb'],
);
$collection->getArrayCopy();
// Returns:
// [
//     'a2' => ['name' => 'Apple', 'nb' => 1],
//     'a1' => ['name' => 'Apple', 'nb' => 10],
//     'b2' => ['name' => 'Banana', 'nb' => 1],
//     'b1' => ['name' => 'Banana', 'nb' => 5],
//     'l' => ['name' => 'Lemon', 'nb' => 1],
//     'o' => ['name' => 'Orange', 'nb' => 1],
// ]`
```

#### `CollectionInterface::filter(?callable $callback = null): static`

Filter items with callback.

```php
$collection = Collection::new([1, 10, 20, 100]);
$collection = $collection->filter(fn($value) => $value >= 20);
$collection->getArrayCopy(); // Returns `[20, 100]`
```

Similar to `array_filter()` function.

#### `CollectionInterface::filterInstanceOf(string|object ...$class): static`

Filter items with object instance comparison.

```php
$collection = Collection::new([new stdClass(), new SimpleXMLElement()]);
$collection = $collection->filterInstanceOf(stdClass::class);
$collection->getArrayCopy(); // Returns `[object<stdClass>]`
```

Similar to `is_a()` function.

#### `CollectionInterface::map(callable $callback): static`

Apply callback on items and return result of callback.

Similar to `array_map()` function.

#### `CollectionInterface::each(callable $callback): static`

Apply callback on items and return items.

Similar to `array_walk()` function.

#### `CollectionInterface::search(callable|mixed $needle): int|string|false`

Search key of item with callback or value.

```php
$collection = Collection::new(['foo', 'bar', '1', 1, 'quxx']);
$collection->search(1); // Returns 2
$collection->search(1, true); // Returns 3
$collection->search(fn($value) => str_starts_with($value, 'bar')); // Returns 1
```

Similar to `array_search()` function.

#### `CollectionInterface::get(int $index = 0): mixed`

Get item at index.

Negative index, starts at end of collection.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection = $collection->get(); // Return `'foo'`
$collection = $collection->get(1); // Return `'bar'`
$collection = $collection->get(-1); // Return `'baz'`
```

#### `CollectionInterface::first(?callable $callback = null): mixed`

Search first item.

If callback given, search first item whose respect callback.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection = $collection->first(); // Return `'foo'`
$collection = $collection->first(fn($value) => str_starts_with('ba', $value)); // Return `'bar'`
```

#### `CollectionInterface::last()`

Search last item.

If callback given, search first item whose respect callback.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection = $collection->last(); // Return `'baz'`
$collection = $collection->last(fn($value) => str_starts_with('ba', $value)); // Return `'baz'`
```

#### `CollectionInterface::slice(int $offset, int|null $length = null): static`

Extract a slice of the collection.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection->slice(0, 2)->getArrayCopy(); // Returns `['foo', 'bar']`
$collection->slice(1)->getArrayCopy(); // Returns `['bar', 'baz']`
$collection->slice(-2, 2)->getArrayCopy(); // Returns `['bar', 'baz']`
$collection->slice(-1)->getArrayCopy(); // Returns `['baz']`
```

Similar to `array_slice()` function.

#### `CollectionInterface::contains(mixed $value, bool $strict = false): bool`

Collection contains value?

```php
$collection = Collection::new(['foo', 'bar', '2', 'baz']);
$collection->contains('foo'); // Returns `true`
$collection->contains('qux'); // Returns `false`
$collection->contains(2); // Returns `true`
$collection->contains(2, true); // Returns `false`
```

Similar to `in_array()` function.

#### `CollectionInterface::chunk(int $length): static`

Chunk collection items into collection of fixed length.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection = $collection->chunck(2); // Returns 2 collections
$collection->getArrayCopy(); // Returns `[['foo', 'bar'], ['baz']]`
```

Similar to `array_chunk()` function.

#### `CollectionInterface::keys(): static`

Get keys of collection items.

```php
$collection = Collection::new(['k1' => 'foo', 1 => 'bar', 'k2' => 'baz']);
$collection->keys()->getArrayCopy(); // Returns `['k1', 1, 'k2']`
```

Similar to `array_keys()` function.

#### `CollectionInterface::values(): static`

Get values of collection items.

```php
$collection = Collection::new(['k1' => 'foo', 1 => 'bar', 'k2' => 'baz']);
$collection->keys()->getArrayCopy(); // Returns `['foo', 'bar', 'baz']`
```

Similar to `array_values()` function.

#### `CollectionInterface::unique(): static`

Get uniques items of collection items.

```php
$collection = Collection::new(['k1' => 'foo', 1 => 'foo', 'bar', 'k2' => 'baz']);
$collection->unique()->getArrayCopy(); // Returns `['k1' => 'foo', 'bar', 'k2' => 'baz']`
```

Similar to `array_unique()` function.

#### `CollectionInterface::flip(): static`

Flip keys and values.

```php
$collection = Collection::new(['k1' => 'foo', 1 => 'foo', 'bar', 'k2' => 'baz']);
$collection->flip()->getArrayCopy(); // Returns `['foo' => 'k1', 'bar' => 0, 'baz' => 'k2']`
```

Similar to `array_flip()` function.

#### `CollectionInterface::reverse(bool $preserve_keys = false): static`

Reverse order of items.

```php
$collection = Collection::new(['k1' => 'foo', 'foo', 'bar', 'k2' => 'baz']);
$collection->reverse()->getArrayCopy(); // Returns `['k2' => 'baz', 0 => 'bar', 1 => 'foo', 'k1' => 'foo']`
$collection->reverse(true)->getArrayCopy(); // Returns `['k2' => 'baz', 1 => 'bar', 0 => 'foo', 'k1' => 'foo']`
```

Similar to `array_reverse()` function.

####

`CollectionInterface::column(string|int|Closure|null $column_key, string|int|Closure|null $index_key = null): static`

Get column value or reindex collection.

```php
$collection = Collection::new([
    ['k1' => 'foo', 'value' => 'foo value'],
    ['k1' => 'bar', 'value' => 'bar value'],
    ['k1' => 'baz', 'value' => 'baz value'],
]);
$collection = $collection->column('k1')->getArrayCopy(); // Returns `['foo', 'bar', 'baz']`
$collection = $collection->column('value', 'k1')->getArrayCopy(); // Returns `['foo' => 'foo value', 'bar' => 'bar value', 'baz' => 'baz value']`
```

Similar to `array_column()` function.

#### `CollectionInterface::rand(int $length = 1): static`

Get random values of collection.

```php
$collection = Collection::new(['foo', 'bar', 'baz']);
$collection = $collection->rand(2)->getArrayCopy(); // Returns 2 values random
```

Similar to `array_rand()` function.

#### `CollectionInterface::sum(): float|int`

Get sum of values.

```php
$collection = Collection::new([1, 2, 3, 4]);
$collection->sum(); // Returns `10`
```

Similar to `array_sum()` function.

#### `CollectionInterface::avg(): float|int`

Get average of values.

```php
$collection = Collection::new([1, 2, 3, 4]);
$collection->avg(); // Returns `2.5`
```

#### `CollectionInterface::median(): float|int`

Get median of values.

```php
$collection = Collection::new([1, 3, 5, 7]);
$collection->median(); // Returns `4`
```

#### `CollectionInterface::variance(): float|int`

Get population variance of values.

```php
$collection = Collection::new([1, 1, 2, 2, 3, 5]);
$collection->variance(); // Returns `1.888888888889`
```

#### `CollectionInterface::deviation(): float|int`

Get population deviation of values.

```php
$collection = Collection::new([1, 3, 5, 7]);
$collection->deviation(); // Returns `2.2360679775`
```

#### `CollectionInterface::reduce(callable $callback, mixed $initial = null): mixed`

Reduce collection with callback and optional initial value.

```php
$collection = Collection::new([1, 2, 3, 4]);
$collection->reduce(fn($carry, $item) => $carry + $item, 10); // Returns `20`
$collection->reduce(fn($carry, $item) => $carry + $item); // Returns `10`
```

Similar to `array_reduce()` function.

### `Collection` methods

`Collection` class implement `ArrayAccess` interface to allow to manipulate collection like an array.

#### `Collection::append(mixed ...$value): static`

Append value(s) to collection.

```php
$collection = Collection::new(['foo', 'bar']);
$collection->append('baz', 'qux')->getArrayCopy(); // Returns `['foo', 'bar', 'baz', 'qux']`
```

Similar to `array_push()` function.

#### `Collection::prepend()`

Prepend value(s) to collection.

```php
$collection = Collection::new(['foo', 'bar']);
$collection->prepend('baz', 'qux')->getArrayCopy(); // Returns `['baz', 'qux', 'foo', 'bar']`
```

Similar to `array_unshift()` function.

#### `Collection::lazy(): CollectionInterface`

New lazy collection from current collection.

### `LazyCollection` methods

Lazy collection use generators to improve performances.
Usage of a collection is unique, but can be chained.
