# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Driver-aware identifier quoting in `AbstractMapper`, `Builder`, `Conditions`, and all Relationship classes using `Statement\Quoted` and `Statement\Expression`
- `Builder::withPivotColumn()` now accepts `StatementInterface|string` (was `string` only)
- Method `Builder::paginate()` for built-in pagination support (offset, cursor, range)
- Parameter `optimized` on `Builder::paginate()` for 2-step primary key pagination (prevents JOIN row duplication)
- Method `Builder::getEntityClass()` to access the entity class name
- Namespace `Hector\Orm\Pagination` with `BuilderOffsetPaginator`, `BuilderCursorPaginator`, `BuilderRangePaginator`
- Method `Builder::chunkPaginate()` to iterate through paginated results in chunks, with `$optimized` support

### Changed

- **BREAKING:** `Orm` is no longer serializable; `__serialize()` and `__unserialize()` now throw `OrmException`
- `AbstractMapper::quotedTuples()` replaces `quoteArrayKeys()`/`quoteArrayValues()` — builds `[Quoted, value]` tuples for driver-aware column quoting
- Relationship join conditions use `Expression` arrays (numeric-keyed) instead of `array_combine` (string-keyed) for driver-aware quoting
- ORM `Conditions::add()` regex accepts both backtick and double-quote styles for relationship detection

### Fixed

- Fix `Related::save()` crash when a related value is `null`

## [1.2.2] - 2026-02-05

_No changes in this release._

## [1.2.1] - 2026-01-13

### Fixed

- Use of the deprecated method ReflectionProperty::setAccessible() with PHP > 8.0

## [1.2.0] - 2026-01-13

### Removed

- Remove unnecessary PhpDoc template

### Fixed

- `Builder::chunk()` now respects pre-defined `limit` and `offset` constraints

## [1.1.0] - 2025-11-21

### Added

- Parameter `$cascade` to method `Entity::save()` (default to false) to persist related entities

### Changed

- `*Many` relationships also accept an `array` instead of just `Collection`
- Improve PHPDoc to enable IDE type inference for concrete entity classes
- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
