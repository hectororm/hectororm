# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `Query\Component\Conditions` relationship detection now relies on the shared `Helper::isColumnReference()` and `Helper::explodePath()` helpers instead of a local regex and `explode('.')`, keeping the same behaviour for relation conditions (e.g. `where('relation.column', ...)`)

### Removed

- Removed unused `AbstractMapper::getPrimaryHash()` and `AbstractMapper::getDataHash()` (dead code, not part of the `Mapper` interface and never called); `getPrimaryHash()` additionally crashed on entities without a primary key

### Fixed

- Fix `OrmFactory::orm()` fataling with `Call to a member function set() on null` when called without a cache (the default): the cache write is now nullsafe (`$cache?->set(...)`), matching the already-nullsafe `$cache?->get(...)`, so the factory is usable without a PSR-16 cache
- Fix `SQLSTATE[HY000] 3065` in optimized pagination (`Builder::paginate(..., optimized: true)`) when ordering by a non-primary-key column: ORDER BY column references are now added to the `SELECT DISTINCT` id subquery (SQL-standard, portable across MySQL/MariaDB/PostgreSQL/SQLite). SQL expressions such as `RAND()` are intentionally not mirrored, to preserve de-duplication.
- Fix inflated total in optimized pagination (`Builder::paginate(..., optimized: true, withTotal: true)`) when the query has a duplicating JOIN: the total now counts distinct primary keys (via the same `SELECT DISTINCT` subquery used to fetch items) instead of the JOIN-inflated row count
- Fix `Undefined array key` warning in `AbstractMapper::getEntityAlteration()` when the entity's stored original data does not contain all checked columns (e.g. after a partial-column fetch): missing columns are now reported as altered instead of emitting a warning
- Fix `Builder::findOrFail()` not throwing `NotFoundException` when called with several primary keys that all match nothing: `find()` returns an empty `Collection` in that case, and `empty()` is always false on an object, so the emptiness is now checked explicitly
- Fix `MagicEntity` magic accessors blocking `#[Hidden]` columns: `__isset()` no longer hides them, so reading, writing and `isset()` work again (hidden becomes an output filter only, aligned with Eloquent/Doctrine)

### Security

- `MagicEntity::jsonSerialize()` and `MagicEntity::__debugInfo()` no longer expose columns declared with `#[Hidden]`, preventing leakage of secrets (passwords, tokens) through `json_encode()` or dumps

## [1.3.0] - 2026-05-12

### Added

- Driver-aware identifier quoting in `AbstractMapper`, `Builder`, `Conditions`, and all Relationship classes using `Statement\Quoted` and `Statement\Expression`
- `Builder::withPivotColumn()` now accepts `StatementInterface|string` (was `string` only)
- Method `Builder::paginate()` for built-in pagination support (offset, cursor, range)
- Parameter `optimized` on `Builder::paginate()` for 2-step primary key pagination (prevents JOIN row duplication)
- Method `Builder::getEntityClass()` to access the entity class name
- Namespace `Hector\Orm\Pagination` with `BuilderOffsetPaginator`, `BuilderCursorPaginator`, `BuilderRangePaginator`
- Method `Builder::chunkPaginate()` to iterate through paginated results in chunks (with `$optimized` support), with callback `function (Collection<T> $items, PaginationInterface $pagination)`. Items are wrapped in an ORM `Collection`, allowing direct `->load([...])` calls in the callback for eager-loading relations. Honors the builder's `limit()` as a global bound across pages, consistent with `Builder::chunk()`

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
