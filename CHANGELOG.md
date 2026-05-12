# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.3.0] - 2026-05-12

### Added

- **hectororm/migration**: New package — database migrations with attribute-based configuration, reversible migrations, event system, and migration tracking
- **hectororm/pagination**: New package — framework-agnostic pagination supporting offset, cursor, and range strategies with encoders, navigators, request handling, and URI building
- **hectororm/connection**: Added `DriverInfo::getIdentifierQuote()` method to expose the driver-specific identifier quoting character
- **hectororm/connection**: Added `DriverCapabilities::hasRenameColumn()` to detect support for `RENAME COLUMN` syntax (false on MySQL < 8.0)
- **hectororm/datatypes**: `RamseyUuidType`: throw `LogicException` with install hint when `ramsey/uuid` is not installed
- **hectororm/orm**: Driver-aware identifier quoting in `AbstractMapper`, `Builder`, `Conditions`, and all Relationship classes using `Statement\Quoted` and `Statement\Expression`
- **hectororm/orm**: `Builder::withPivotColumn()` now accepts `StatementInterface|string` (was `string` only)
- **hectororm/orm**: Method `Builder::paginate()` for built-in pagination support (offset, cursor, range)
- **hectororm/orm**: Parameter `optimized` on `Builder::paginate()` for 2-step primary key pagination (prevents JOIN row duplication)
- **hectororm/orm**: Method `Builder::getEntityClass()` to access the entity class name
- **hectororm/orm**: Namespace `Hector\Orm\Pagination` with `BuilderOffsetPaginator`, `BuilderCursorPaginator`, `BuilderRangePaginator`
- **hectororm/orm**: Method `Builder::chunkPaginate()` to iterate through paginated results in chunks (with `$optimized` support), with callback `function (Collection<T> $items, PaginationInterface $pagination)`. Items are wrapped in an ORM `Collection`, allowing direct `->load([...])` calls in the callback for eager-loading relations. Honors the builder's `limit()` as a global bound across pages, consistent with `Builder::chunk()`
- **hectororm/query**: `Statement\Expression` for composing heterogeneous SQL fragments (`StatementInterface|string`) with deferred driver-aware resolution
- **hectororm/query**: `Statement\Quoted` for driver-aware deferred identifier quoting (supports composite `schema.table.column` and `*` wildcard)
- **hectororm/query**: `Statement\Encapsulated` wrapper class for explicit sub-expression parenthesization
- **hectororm/query**: `CompoundStatementInterface` marker interface for statements (queries, grouped conditions) that should be auto-encapsulated as sub-expressions
- **hectororm/query**: Tuple format `[column, value]` in `Assignments::assignments()` and `Conditions::equals()` to allow `StatementInterface` column names (e.g. `Quoted`)
- **hectororm/query**: Driver-aware identifier quoting via `DriverInfo` parameter on `StatementInterface::getStatement()`
- **hectororm/query**: `Helper::quote()` and `Helper::trim()` now accept a `$quote` parameter for driver-specific quote character
- **hectororm/query**: `Helper::quote()` now escapes embedded quote characters by doubling them
- **hectororm/query**: Method `Component\Order::getOrder()` to get defined order
- **hectororm/query**: Method `QueryBuilder::paginate()` for built-in pagination support (offset, cursor, range)
- **hectororm/query**: Namespace `Pagination` with `QueryOffsetPaginator`, `QueryCursorPaginator`, `QueryRangePaginator`
- **hectororm/query**: Namespace `Sort` with `SortInterface`, `Sort`, `MultiSort` and `SortConfig` for type-safe, composable sorting
- **hectororm/query**: Method `QueryBuilder::applySort(SortInterface)` to apply a sort object to the query builder
- **hectororm/query**: Cursor position validation in `QueryCursorPaginator` (columns match, scalar values only)
- **hectororm/query**: Method `QueryBuilder::chunkPaginate()` to iterate through all pages, with callback `function (mixed $items, PaginationInterface $pagination)`. Honors the builder's `limit()` as a global bound across pages by adjusting the next request via `PaginationRequestInterface::withPerPage()`
- **hectororm/schema**: Schema Plan system for declarative schema migrations (tables, indexes, foreign keys, views)

### Changed

- **hectororm/orm**: **BREAKING:** `Orm` is no longer serializable; `__serialize()` and `__unserialize()` now throw `OrmException`
- **hectororm/orm**: `AbstractMapper::quotedTuples()` replaces `quoteArrayKeys()`/`quoteArrayValues()` — builds `[Quoted, value]` tuples for driver-aware column quoting
- **hectororm/orm**: Relationship join conditions use `Expression` arrays (numeric-keyed) instead of `array_combine` (string-keyed) for driver-aware quoting
- **hectororm/orm**: ORM `Conditions::add()` regex accepts both backtick and double-quote styles for relationship detection
- **hectororm/query**: **BREAKING:** Removed `bool $encapsulate` parameter from `StatementInterface::getStatement()` — callers needing parentheses should use `Statement\Encapsulated` wrapper instead
- **hectororm/query**: `Statement\Row` now accepts `StatementInterface|string` values (was `string` only)
- **hectororm/query**: `Statement\Row::getStatement()` now always returns parenthesized format `(val1, val2)`
- **hectororm/query**: `Component\AbstractComponent::getSubStatement()` and `getSubStatementValue()` auto-encapsulate `CompoundStatementInterface` instances

### Deprecated

- **hectororm/schema**: The `$quoted` parameter on `Schema::getName()`, `Table::getName()`, `Table::getSchemaName()`, `Table::getFullName()`, `Table::getColumnsName()`, `Column::getName()`, `Column::getFullName()`, `Index::getColumnsName()`, `ForeignKey::getColumnsName()`, and `ForeignKey::getReferencedColumnsName()` is deprecated; use `Hector\Query\Statement\Quoted` for driver-aware identifier quoting instead

### Removed

- **hectororm/query**: `Component\EncapsulateHelperTrait` — replaced by `Statement\Encapsulated` and `CompoundStatementInterface`

### Fixed

- **hectororm/collection**: `Collection::first()` and `Collection::last()` now correctly return `false` and `0` values instead of `null`
- **hectororm/collection**: `Collection::__construct()` now properly handles `Closure` arguments (invokes the closure to produce an iterable)
- **hectororm/collection**: `Collection::rand()` no longer crashes on empty collections
- **hectororm/collection**: `LazyCollection::unique()` no longer uses `md5((string)$item)` which failed on non-string items; now compares items directly like `array_unique()`
- **hectororm/connection**: Use `PDO::PARAM_NULL` for `null` values in `BindParam::findDataType()` instead of `PDO::PARAM_STR`
- **hectororm/datatypes**: Fix missing `throw` in `EnumType::fromSchema()` when expected type does not match configured enum class
- **hectororm/datatypes**: Fix inverted type check in `RamseyUuidType::fromSchema()` that rejected valid `UuidInterface` properties
- **hectororm/datatypes**: Fix `DefaultType::toSchema()` return type from `string` to `mixed` to match interface and actual behavior
- **hectororm/datatypes**: Fix `JsonType::equals()` comparing decoded entity data with JSON string, causing unnecessary UPDATE queries
- **hectororm/datatypes**: Fix typo in `TypeSet`: `longblog` renamed to `longblob`
- **hectororm/datatypes**: Fix `SetType::equals()` crashing with `TypeError` when entity data is an array
- **hectororm/orm**: Fix `Related::save()` crash when a related value is `null`
- **hectororm/query**: Escape LIKE wildcard characters (`%`, `_`, `\`) in `whereContains`, `whereStartsWith`, `whereEndsWith` and their Having equivalents
- **hectororm/schema**: Sanitize identifiers in SQLite schema generator to prevent SQL injection via PRAGMA statements
- **hectororm/schema**: PHPUnit deprecation warnings in tests (Generator passed as `$haystack`)
- **hectororm/schema**: Table charset detection on MariaDB 11.4.5+ by joining `information_schema.collation_character_set_applicability` on `FULL_COLLATION_NAME`

## [1.2.2] - 2026-02-05

### Fixed

- **hectororm/query**: Closure binding issue in `Conditions::getStatement()` by removing unnecessary reference in foreach loop

## [1.2.1] - 2026-01-13

### Fixed

- **hectororm/orm**: Use of the deprecated method ReflectionProperty::setAccessible() with PHP > 8.0

## [1.2.0] - 2026-01-13

### Added

- **hectororm/collection**: Method `CollectionInterface::join()` to join items with a glue string and optional final glue
- **hectororm/collection**: Method `CollectionInterface::groupBy()` to group items by a key or callback result

### Removed

- **hectororm/orm**: Remove unnecessary PhpDoc template

### Fixed

- **hectororm/datatypes**: Argument `$type` renamed to `$numericType` in `NumericType` constructor
- **hectororm/orm**: `Builder::chunk()` now respects pre-defined `limit` and `offset` constraints

## [1.1.0] - 2025-11-21

### Added

- Tests with MySQL 9.5 and MariaDB 12.1 on GitHub workflows
- **hectororm/orm**: Parameter `$cascade` to method `Entity::save()` (default to false) to persist related entities

### Changed

- **hectororm/collection**: Improve PHPDoc to enable IDE type inference for concrete entity classes
- **hectororm/collection**: Performed code cleanup and refactoring using Rector
- **hectororm/connection**: Performed code cleanup and refactoring using Rector
- **hectororm/datatypes**: Performed code cleanup and refactoring using Rector
- **hectororm/orm**: `*Many` relationships also accept an `array` instead of just `Collection`
- **hectororm/orm**: Improve PHPDoc to enable IDE type inference for concrete entity classes
- **hectororm/orm**: Performed code cleanup and refactoring using Rector
- **hectororm/query**: Perf: replace a loop with foreach to avoid repeated count()
- **hectororm/query**: Performed code cleanup and refactoring using Rector
- **hectororm/schema**: Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

### 🚀 First Monorepo Release

This is the first official release of HectorORM as a monorepo.

All core components are now developed, maintained, and versioned together in a single repository:

- `hectororm/collection`
- `hectororm/connection`
- `hectororm/data-types`
- `hectororm/orm`
- `hectororm/query`
- `hectororm/schema`

#### Migration Notice

Previous component versions and their individual changelogs are now unified.  
Future changes, bug fixes, and improvements will be tracked **here, in the main monorepo changelog**.

See the commit history for all legacy changes.
