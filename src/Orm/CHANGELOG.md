# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `Entity\ReflectionEntity::getTable()` now caches the resolved `Table` (the `??=` assignment that was intended but missing), avoiding a repeated schema-container lookup on every query build, hydration, persist and relationship resolution. No behaviour change
- `Query\Component\Conditions` relationship detection now relies on the shared `Helper::isColumnReference()` and `Helper::explodePath()` helpers instead of a local regex and `explode('.')`, keeping the same behaviour for relation conditions (e.g. `where('relation.column', ...)`)

### Removed

- Removed unused `AbstractMapper::getPrimaryHash()` and `AbstractMapper::getDataHash()` (dead code, not part of the `Mapper` interface and never called); `getPrimaryHash()` additionally crashed on entities without a primary key
- Removed the unreachable loose `!=` fallback in `AbstractMapper::getEntityAlteration()`: `ReflectionEntity::getType()` always returns a `TypeInterface` (a mapped type, a `StringType` fallback, or it throws) and every `TypeInterface::equals()` returns a strict `bool`, so the column comparison now relies solely on the type's `equals()`

### Fixed

- Fix `OrmFactory::orm()` fataling with `Call to a member function set() on null` when called without a cache (the default): the cache write is now nullsafe (`$cache?->set(...)`), matching the already-nullsafe `$cache?->get(...)`, so the factory is usable without a PSR-16 cache
- Fix `#[Type]` precedence in `ReflectionEntity`: a `#[Type('col', ...)]` declared in a subclass was overwritten by the same column declared in a parent class (the hierarchy walk unconditionally reassigned the column key). The child now wins (first-write-wins), consistent with how `#[Mapper]` and `#[Table]` resolution already stop at the first match
- Fix `Entity::load()` fataling with `Call to a member function load() on null` when a nested relation is requested on a `ManyToOne`/`HasOne` that resolves to `null` (e.g. `$film->load(['original_language' => ['films']])` when `original_language_id` is `NULL`): the nested load is now nullsafe and is skipped when the relation is null
- Fix silently losing a modified primary key on `update`: changing a primary-key value on a loaded entity and saving it used to build the `UPDATE` `WHERE` clause from the new (non-existent) key while stripping the key from the `SET` clause, so no row was updated and the entity was silently reverted to its original key on refresh. `AbstractMapper::updateEntity()` now throws a `MapperException` when the primary key of a loaded entity has been mutated
- `Relationship\ManyToOne::valid()` now accepts a subclass of the target entity (`instanceof`) instead of requiring the exact class, so a related entity that extends the target is no longer rejected
- `Mapper\AbstractMapper::refreshEntity()` no longer falls back to a condition built from all the entity's columns (or an unconditioned `SELECT`) when no primary value is available: it now throws a clear `MapperException` instead of risking hydrating an arbitrary row. The not-found message typo ("unexciting") is fixed to "non-existing"
- Fix `OneToMany::linkNative()` not clearing detached entities: after deleting the detached children it now calls `$foreign->clearDetached()` (as `ManyToMany::linkNative()` already does). Previously a second save of the parent re-iterated the already-deleted children and threw `OrmException('Entity does not exists in storage')`
- Fix `Entity::isEqualTo()` and `OneToMany::linkNative()` dropping falsy primary-/foreign-key values (`0`, `'0'`): `array_filter()` is now called with a `null`-only callback so an entity (or relation) whose key is `0` is no longer treated as having no key. `isEqualTo()` now filters both compared primary-key arrays identically (previously only the left side was filtered, breaking comparison of composite keys containing a falsy value). This also fixes `Collection::contains()` for such entities
- Fix `ManyToMany::linkNative()` doubling the in-memory related collection on every `save()`: when the relation was already loaded, `Related::get()` returned the very same collection instance that was passed in as `$foreign`, so appending it onto itself duplicated its content (and triggered a redundant pivot `EXISTS`/`UPDATE` per duplicate). The append loop is now skipped when the two are the same instance, and otherwise only adds entities not already contained
- Fix `ManyToMany::linkNative()` losing pivot foreign keys and never persisting pivot data: pivot keys (foreign keys, from `getPivotData()`) and additional pivot data (`getPivot()->getData()`) are now handled separately. Previously, when the foreign entity carried a `PivotData`, the resolved foreign keys were overwritten by the (possibly empty) extra data — causing an unjustified `RelationException` when re-attaching an already-loaded entity (empty `getData()`), an `INSERT` without the foreign-key columns when the extra data was non-empty, and an `UPDATE` that rewrote the keys to themselves and never persisted pivot-data changes. The `INSERT` now merges keys and extra data, and the `UPDATE` writes only the additional data (skipped when there is none)
- Fix `RegularRelationship::getBuilder()` and `ManyToMany::getBuilder()` emitting an invalid `IN (  )` clause when the source key-set is empty (e.g. an entity whose foreign key is `NULL`, such as `film.original_language_id`): both now check the resolved key values and return an unfiltered builder when there are none, instead of guarding only on the entity count. `RegularRelationship::get()` now also computes its emptiness guard on the filtered entities (consistent with the builder it then calls)
- Fix loose `==`/`!=` key-tuple comparison during eager-loading association (`ManyToOne`, `OneToOne`, `OneToMany`, `ManyToMany`): a new normalized per-key comparison keeps the int/string tolerance (e.g. `5` and `"5"`) but no longer coerces numeric-looking strings (`"01"` vs `"1"`, `"1e2"` vs `"100"`) nor conflates `null` with `0`, preventing wrong associations on string-typed numeric keys
- Fix `Orm::persist()` failing on any pending entity: `EntityStorage::getIterator()` wrapped the underlying `WeakMap` in an `IteratorIterator`, which yields the statuses (map values) instead of the entities, so `persist()` passed integers to `persistEntity()` and always threw `OrmException('Error while persisting entities')`. Iteration now yields the `Entity` instances.
- Fix `Orm::persist()` calling `beginTransaction()`/`commit()`/`rollBack()` on `ConnectionSet`, which did not implement them (fatal `Error`); the transaction now spans every connection of the set
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
