# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Collection::unique()` and `LazyCollection::unique()` now accept an optional `Closure` that computes the comparison key of each item (`$value`, `$key`); items whose computed keys are strictly identical (`===`) are de-duplicated. This allows de-duplicating objects or arrays (e.g. by an `id`) without relying on their string cast. Without a callback the behaviour is unchanged

### Changed

- `Collection::chunk()` no longer accepts the undocumented `?callable $callback` second argument that `LazyCollection::chunk()` and `CollectionInterface::chunk(int $length)` did not have; it now matches the interface and `array_chunk()`. Use `->chunk($length)->map($callback)` for the previous behaviour
- Documented `LazyCollection`'s single-use contract: terminal operations drain the underlying generator, so the data must be materialised with `collect()` (or a `Collection` used) to be traversed more than once

### Fixed

- `Collection::median()` (and `LazyCollection::median()`, which delegates to it) returned a wrong result on unsorted data: it read the middle element by key after an `asort()` that preserves keys, instead of by position. Values are now re-indexed after sorting
- `LazyCollection::slice()` (and therefore `get()`) no longer drains the whole source for a positive offset/length window: once the window is filled it stops consuming the underlying generator, restoring the lazy contract and making `get()`/`slice()` usable on very large or infinite generators
- `LazyCollection::unique()` now de-duplicates items consistently with `Collection::unique()` / `array_unique()`: items are compared by their string cast (default `SORT_STRING` semantics) instead of a loose `==`, so it no longer merges distinct values that share the same numeric value (e.g. `'1e3'`/`'1000'`)
- `Collection::search()` and `LazyCollection::search()` no longer treat a string/array that happens to be callable (e.g. `'trim'`, `'date'`) as a predicate: only a `Closure` is now used as a predicate, any other value is searched as a value (previously `is_callable($needle)` invoked such strings as functions instead of comparing them)
- `Collection::multiSort()` / `LazyCollection::multiSort()` now require at least one comparator in their signature (`multiSort(callable $callback, callable ...$_callback)`); calling them without any argument used to invoke a `null` comparator and fail with "null is not callable", and now raises a native `ArgumentCountError`
- `LazyCollection::flip()` no longer throws a fatal `TypeError` on non-scalar items; such values are now skipped, mirroring `array_flip()`'s "entry skipped" behaviour (`Collection::flip()` already delegated to `array_flip()` and is unchanged)

## [1.3.0] - 2026-05-12

### Fixed

- `Collection::first()` and `Collection::last()` now correctly return `false` and `0` values instead of `null`
- `Collection::__construct()` now properly handles `Closure` arguments (invokes the closure to produce an iterable)
- `Collection::rand()` no longer crashes on empty collections
- `LazyCollection::unique()` no longer uses `md5((string)$item)` which failed on non-string items; now compares items directly like `array_unique()`

## [1.2.2] - 2026-02-05

_No changes in this release._

## [1.2.1] - 2026-01-13

_No changes in this release._

## [1.2.0] - 2026-01-13

### Added

- Method `CollectionInterface::join()` to join items with a glue string and optional final glue
- Method `CollectionInterface::groupBy()` to group items by a key or callback result

## [1.1.0] - 2025-11-21

### Changed

- Improve PHPDoc to enable IDE type inference for concrete entity classes
- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
