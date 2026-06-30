# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2026-06-30

### Added

- **hectororm/collection**: `Collection::unique()` and `LazyCollection::unique()` now accept an optional `Closure` that computes the comparison key of each item (`$value`, `$key`); items whose computed keys are strictly identical (`===`) are de-duplicated. This allows de-duplicating objects or arrays (e.g. by an `id`) without relying on their string cast. Without a callback the behaviour is unchanged
- **hectororm/connection**: Added `DriverCapabilities::hasTransactionalDdl()` to detect whether DDL statements participate in the surrounding transaction (true on SQLite/PostgreSQL, false on MySQL/MariaDB). **Note:** this adds a method to the `DriverCapabilities` interface, so any third-party implementation must implement it
- **hectororm/connection**: Added `ConnectionSet::beginTransaction()`, `ConnectionSet::commit()` and `ConnectionSet::rollBack()` to start/commit/roll back a transaction across every connection of the set
- **hectororm/datatypes**: `AbstractType::assertNullable()` helper to validate that a `null` value is permitted by the expected type (always allowed when no `ExpectedType` is provided)
- **hectororm/datatypes**: `TypeSet` now maps the `time`, `bit`, `binary` and `varbinary` MySQL column types (previously they threw `UnexpectedValueException`)
- **hectororm/datatypes**: New `DecimalType` for exact `DECIMAL`/`NUMERIC` columns: it keeps the value as its canonical numeric string instead of going through a lossy PHP `float`, and hydrates a `\BcMath\Number` (PHP 8.4+) when the entity property is typed as such. `TypeSet` now maps `decimal` and `numeric` to this type automatically (the approximate `float`/`double` types are unchanged), preventing precision loss on high-precision values such as monetary amounts
- **hectororm/query**: `Helper::isColumnReference()` to detect whether a value is a plain (possibly qualified/quoted) column reference, as opposed to an SQL expression/function, closure or sub-query. Quoted segments may contain dots/spaces, bare segments accept Unicode, and numeric literals are rejected
- **hectororm/query**: `Helper::explodePath()` to split a (possibly qualified/quoted) SQL identifier path on its dot separators, ignoring dots enclosed in a matching pair of identifier quotes, with an `explode()`-like `$limit` and a configurable `$quotes` parameter (defaults to backtick and double quote; pass `''` to split unconditionally)
- **hectororm/query**: `Pagination\AbstractQueryPaginator::extractColumnOrderItems()` returning the ORDER BY items that are plain column references (deterministic and materialisable)
- **hectororm/query**: `Helper::unquote()` to de-quote an identifier: trims surrounding whitespace then strips a matching enclosing quote pair (undoubling the inner quote character), with a configurable set of quote characters
- **hectororm/query**: `Pagination\AbstractQueryPaginator::fetchTotal()` extension point so subclasses can customise how the total is counted (e.g. count distinct primary keys instead of JOIN-inflated rows)
- **hectororm/query**: `Statement\RandomFunction` rendering the driver-specific random function (`RAND()` on MySQL/MariaDB, `RANDOM()` on SQLite/PostgreSQL) via `DriverInfo`
- **hectororm/schema**: `Hector\Schema\Plan\Raw` to express a column default as a raw SQL expression (e.g. `new Raw('CURRENT_TIMESTAMP()')`), emitted verbatim instead of being quoted as a string literal
- **hectororm/schema**: `TableOperation::addColumn()` and `AlterTable::modifyColumn()` now auto-detect `hasDefault` when it is omitted (`null`): the `DEFAULT` clause is enabled when a `Raw` expression or a non-null value is provided

### Changed

- **hectororm/collection**: `Collection::chunk()` no longer accepts the undocumented `?callable $callback` second argument that `LazyCollection::chunk()` and `CollectionInterface::chunk(int $length)` did not have; it now matches the interface and `array_chunk()`. Use `->chunk($length)->map($callback)` for the previous behaviour
- **hectororm/collection**: Documented `LazyCollection`'s single-use contract: terminal operations drain the underlying generator, so the data must be materialised with `collect()` (or a `Collection` used) to be traversed more than once
- **hectororm/connection**: `LogEntry::__construct()` now requires a non-null `string $statement` (was `?string`): a log entry always represents a statement, and `getStatement(): string` previously threw a `TypeError` when the entry was built with `null`. The constructor now rejects `null` outright. No production caller is affected (`Logger::newEntry()` already typed `$statement` as non-null)
- **hectororm/connection**: `ConnectionSet::addConnection()` now throws a `ConnectionException` when a connection with the same name is already registered, instead of silently overwriting (and discarding) the previous one, which is a configuration error
- **hectororm/datatypes**: `AbstractType::equals()` (the dirty-checking base used by most types) no longer compares with loose `==`. `null` is equal only to `null`, and scalars are compared by their string form, so real changes previously hidden (e.g. `"1e3"` vs `"1000"`, `"1.0"` vs `"1"`, `null` vs `""`) are now detected and persisted, while the legitimate int/float vs numeric-string juggling between the entity and the database value (e.g. `1` vs `"1"`) still compares as equal
- **hectororm/datatypes**: All concrete types now handle `null` consistently: `fromSchema(null)` returns `null` when the expected type allows it (or when no expected type is given) and throws `ValueException` otherwise; `toSchema(null)` returns `null` so nullable columns persist as SQL `NULL`. The return types of `BooleanType::toSchema()` (`?int`) and `StringType`/`DateTimeType`/`UuidType`/`RamseyUuidType`/`SetType` `toSchema()` (`?string`) were widened accordingly
- **hectororm/migration**: The `DbTracker` tracking table now has an `id` auto-increment column (driver-specific: `INTEGER PRIMARY KEY AUTOINCREMENT` on SQLite, `INT AUTO_INCREMENT PRIMARY KEY` on MySQL/MariaDB, `INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY` on PostgreSQL) used to replay applied migrations in their exact application order, and `migration_id` is now a `UNIQUE` column instead of the primary key. `DbTracker` now ensures the table exists (via `CREATE TABLE IF NOT EXISTS`) before reading it, then orders by `id`. A tracking table created by a previous (unreleased) iteration without the `id` column can no longer be read and raises a clear `MigrationException` asking to upgrade the tracking table schema (no silent fallback, no automatic table migration)
- **hectororm/migration**: `DbTracker` now stores `applied_at` in UTC (`gmdate()`) instead of the local timezone
- **hectororm/migration**: `MigrationRunner` now records the tracking write inside the migration transaction when the driver supports transactional DDL (SQLite/PostgreSQL), so a migration and its tracking row commit or roll back atomically
- **hectororm/orm**: `Entity\ReflectionEntity::getTable()` now caches the resolved `Table` (the `??=` assignment that was intended but missing), avoiding a repeated schema-container lookup on every query build, hydration, persist and relationship resolution. No behaviour change
- **hectororm/orm**: `Query\Component\Conditions` relationship detection now relies on the shared `Helper::isColumnReference()` and `Helper::explodePath()` helpers instead of a local regex and `explode('.')`, keeping the same behaviour for relation conditions (e.g. `where('relation.column', ...)`)
- **hectororm/pagination**: The `OffsetPaginationRequest` and `RangePaginationRequest` constructors now throw `InvalidArgumentException` on invalid values (`page`/`perPage` < 1, `start` < 0, `end` < `start`), consistent with the `OffsetPagination` and `RangePagination` model classes. The `fromRequest()`/`fromHeader()` factories keep clamping untrusted HTTP input silently and never throw.
- **hectororm/query**: Cursor pagination now ignores ORDER BY expressions that are not column references (e.g. `ORDER BY RAND()`) instead of producing an invalid cursor navigation; if no column-based ORDER BY remains, it throws as before
- **hectororm/query**: `Statement\Quoted` now splits composite identifiers with `Helper::explodePath()`, so a dot enclosed in identifier quotes (e.g. `` `a.b`.`c` ``) is no longer mistaken for a segment separator
- **hectororm/query**: `Helper::trim()` second parameter is now the set of characters to strip (defaults to whitespace) instead of a single quote character; identifier de-quoting moved to the new `Helper::unquote()`. `Statement\Quoted`, the alias handling of `Component\Columns`/`Component\Join`/`Component\Table`, and `Pagination\AbstractQueryPaginator::normalizeColumnKey()` now rely on `Helper::unquote()`/`Helper::explodePath()`

### Removed

- **hectororm/orm**: Removed unused `AbstractMapper::getPrimaryHash()` and `AbstractMapper::getDataHash()` (dead code, not part of the `Mapper` interface and never called); `getPrimaryHash()` additionally crashed on entities without a primary key
- **hectororm/orm**: Removed the unreachable loose `!=` fallback in `AbstractMapper::getEntityAlteration()`: `ReflectionEntity::getType()` always returns a `TypeInterface` (a mapped type, a `StringType` fallback, or it throws) and every `TypeInterface::equals()` returns a strict `bool`, so the column comparison now relies solely on the type's `equals()`

### Fixed

- **hectororm/collection**: `Collection::median()` (and `LazyCollection::median()`, which delegates to it) returned a wrong result on unsorted data: it read the middle element by key after an `asort()` that preserves keys, instead of by position. Values are now re-indexed after sorting
- **hectororm/collection**: `LazyCollection::slice()` (and therefore `get()`) no longer drains the whole source for a positive offset/length window: once the window is filled it stops consuming the underlying generator, restoring the lazy contract and making `get()`/`slice()` usable on very large or infinite generators
- **hectororm/collection**: `LazyCollection::unique()` now de-duplicates items consistently with `Collection::unique()` / `array_unique()`: items are compared by their string cast (default `SORT_STRING` semantics) instead of a loose `==`, so it no longer merges distinct values that share the same numeric value (e.g. `'1e3'`/`'1000'`)
- **hectororm/collection**: `Collection::search()` and `LazyCollection::search()` no longer treat a string/array that happens to be callable (e.g. `'trim'`, `'date'`) as a predicate: only a `Closure` is now used as a predicate, any other value is searched as a value (previously `is_callable($needle)` invoked such strings as functions instead of comparing them)
- **hectororm/collection**: `LazyCollection::chunk()` now throws a `ValueError` for a length `<= 0`, consistently with `array_chunk()` and `Collection::chunk()` (it previously built chunks of a single element)
- **hectororm/collection**: `Collection::multiSort()` / `LazyCollection::multiSort()` now require at least one comparator in their signature (`multiSort(callable $callback, callable ...$_callback)`); calling them without any argument used to invoke a `null` comparator and fail with "null is not callable", and now raises a native `ArgumentCountError`
- **hectororm/collection**: `LazyCollection::flip()` no longer throws a fatal `TypeError` on non-scalar items; such values are now skipped, mirroring `array_flip()`'s "entry skipped" behaviour (`Collection::flip()` already delegated to `array_flip()` and is unchanged)
- **hectororm/connection**: Fix the transaction counter desyncing when `beginTransaction()` fails: the counter is now incremented only after `PDO::beginTransaction()` succeeds (previously a failure left the counter incremented with no real transaction, permanently routing reads to the write PDO and making the next `commit()` call `PDO::commit()` without an active transaction). Nesting semantics are preserved (only the outermost call touches the real PDO)
- **hectororm/connection**: Fix `Connection::commit()`/`rollBack()` throwing `There is no active transaction` after a DDL statement issued an implicit `COMMIT` (MySQL/MariaDB): both now guard against `PDO::inTransaction()` and become a no-op when the transaction was already closed, instead of failing. This previously left a DDL migration applied but untracked (reported as failed and stuck pending)
- **hectororm/connection**: Fix `Connection::yieldColumn()` truncating the result set when a column value is boolean `false`: it now traverses the statement in `PDO::FETCH_COLUMN` mode instead of looping on `false !== fetchColumn()`, which conflated a `false` value with end-of-cursor (observable with PostgreSQL boolean columns)
- **hectororm/datatypes**: `StringType::fromSchema()` now resolves backed-enum typed columns: the `is_a()` class-string check was missing its third `$allow_string` argument, so the `BackedEnum` branch was never reached and valid enum values were rejected with a "not builtin" error
- **hectororm/datatypes**: `StringType::toSchema()` now serializes a `BackedEnum` to its backing value, making the round-trip symmetric with `fromSchema()`; previously an entity with a backed-enum property could be hydrated but not persisted (the enum was neither scalar nor `Stringable`, so it raised a "cast" error)
- **hectororm/datatypes**: `StringType::fromSchema()` now hydrates int-backed enums from the numeric strings returned by PDO (the value is coerced to the enum backing type) and wraps the native `TypeError`/`ValueError` raised by `Enum::from()` in a `ValueException`; previously an int-backed enum could never be hydrated from MySQL (raw `TypeError`) and an unknown enum value leaked a raw `ValueError`
- **hectororm/datatypes**: `DateTimeType::fromSchema()` now raises a `ValueException` when an invalid date is requested as an `int`, instead of silently casting `strtotime()`'s `false` to `0` (1970-01-01)
- **hectororm/datatypes**: `JsonType::fromSchema()` null handling is now reachable (the `assertScalar()` call previously rejected `null` before the null branch could run)
- **hectororm/datatypes**: `JsonType::fromSchema()` with a `stdClass`-typed property now uses `JSON_THROW_ON_ERROR` instead of the misplaced encode-only `JSON_FORCE_OBJECT` flag (which `json_decode` ignored): invalid JSON now raises a `ValueException` instead of returning `null` silently, and the decoded value is cast to `stdClass` so a JSON array or scalar always yields an object instead of a PHP array/scalar
- **hectororm/datatypes**: `JsonType::equals()` now compares both sides canonically (recursive key sorting) so object/associative-array values with a different key order are considered equal, avoiding spurious `UPDATE` queries; it also no longer triggers a `TypeError` when the schema side is already a decoded array
- **hectororm/datatypes**: `UuidType::fromSchema()` no longer triggers a `strlen(null)` deprecation and casts the value to string before length checks
- **hectororm/datatypes**: `UuidType` now validates the value as a 16-byte binary string, a 32-character hexadecimal string or a 36-character dashed UUID before formatting; a malformed value now raises a `ValueException` instead of being returned unchanged (`fromSchema()`), emitting a `hex2bin()` warning and returning `false` (`toSchema()` binary), or raising a raw `vsprintf()` "must contain 8 items" error (`toSchema()` string)
- **hectororm/datatypes**: `SetType::equals()` now treats an empty string and an empty array as the same empty set (empty members are stripped), avoiding a spurious "not equal" result that triggered unnecessary `UPDATE` queries
- **hectororm/datatypes**: `JsonType::equals()` no longer uses `empty()` against a NULL column, which wrongly treated meaningful falsy JSON values (`0`, `0.0`, `false`, `[]`, `'0'`) as unchanged so they were never persisted; only an empty JSON payload (empty string) is now considered equal to a NULL column
- **hectororm/datatypes**: `DateTimeType` now applies a single, consistent timezone to every conversion path. Previously the numeric/timestamp path (`@timestamp`) rendered UTC wall-clock while the string and object paths used the ambient PHP timezone, so the same instant produced different values and round-trips could shift. An optional `$timezone` constructor argument has been added (defaulting to `null`, i.e. the ambient `date_default_timezone_get()`, which preserves the previous string-path behaviour while making the numeric path agree with it)
- **hectororm/datatypes**: `NumericType` now rejects non-numeric strings with a `ValueException` instead of silently coercing them to `0`/`0.0` through `settype()` (e.g. `fromSchema('abc')` previously returned `0`). Booleans and already-numeric values are unaffected, and the legitimate `float`-to-`int` truncation driven by the configured numeric type is preserved
- **hectororm/datatypes**: `BooleanType` now resolves the textual values `"true"`/`"false"` before casting, so a column holding the string `"false"` is no longer interpreted as `true` when the entity property is typed `bool`/`int` (the textual normalization previously applied only when no expected type was set, and `settype((bool) "false")` yields `true`)
- **hectororm/datatypes**: `EnumType` now hydrates int-backed enums from the numeric strings returned by PDO (the value is coerced to the enum backing type), so an int-backed enum is no longer impossible to read from MySQL (raw `TypeError`); it also wraps the native `TypeError`/`ValueError` raised by `from()`/`toSchema()` in a `ValueException`. In "try" mode a type mismatch now yields `null` instead of a `TypeError`
- **hectororm/datatypes**: `SetType::fromSchema()` now hydrates an empty SET as an empty array instead of `['']` (a single empty member), and `SetType::toSchema()` now accepts the raw comma-separated string in addition to an array, so a SET property typed as `string` is persistable (round-trip symmetric with `fromSchema()`)
- **hectororm/migration**: `FileTracker` now writes its JSON tracking file atomically (write to a temporary file in the same directory, then `rename()` over the target) and detects partial writes (bytes written must match the payload), so a crash mid-write can no longer truncate or corrupt the tracking file (the source of truth)
- **hectororm/migration**: `DirectoryProvider` now orders recursively-scanned migrations by file name instead of by absolute path, so a timestamp-prefixed migration in a subdirectory is no longer mis-ordered relative to one at the root (sorting full paths interleaved directories, e.g. `20260301_Root.php` sorted before `sub/20260101_Nested.php`). `Psr4Provider` keeps ordering by fully-qualified class name (it has no timestamp-in-filename convention); the file sort key is exposed via a protected `sortKey()` hook
- **hectororm/migration**: Directory/PSR-4 migration providers now reject a non-instantiable migration class (e.g. an abstract class that implements `MigrationInterface`) with a clear `MigrationException` instead of letting `new` raise a raw `Error` (the class is checked with `ReflectionClass::isInstantiable()` before direct construction; classes built by the PSR-11 container are left to the container)
- **hectororm/migration**: `DbTracker::markReverted()` is now idempotent: deleting a migration it does not track is a no-op instead of throwing. This stops a `ChainTracker` from breaking mid-way (leaving trackers out of sync) when reverting a migration that only some trackers recorded
- **hectororm/migration**: `ChainTracker` now validates its strategy in the constructor (throwing `MigrationException` on an unknown value) instead of only failing later on the first read operation
- **hectororm/migration**: `MigrationRunner` now runs a migration's own `up()`/`down()` (the user code that builds the `Plan`) inside the failure handling: an exception thrown while building the plan is logged, dispatched as a `MigrationFailedEvent` and wrapped in a `MigrationException` (with the original as `previous`), like an execution failure — previously it escaped raw. A failing `rollBack()` in the error path no longer masks the original exception, and a rollback is attempted only when a transaction was actually started
- **hectororm/migration**: `MigrationRunner` no longer leaves the database in an "applied but untracked" state when tracking fails: on transactional-DDL drivers the whole migration is rolled back, and on other drivers a tracking failure now raises a `MigrationException` and dispatches a `MigrationFailedEvent` instead of throwing silently outside the error path
- **hectororm/migration**: `DbTracker` now replays migrations in their exact application order via the `id` auto-increment column. Previously it ordered by `applied_at` (second precision) then `migration_id` (alphabetical), so migrations applied within the same second were re-ordered alphabetically, causing `down()` to revert them in the wrong order
- **hectororm/migration**: `DbTracker::markApplied()` is now safe against concurrent processes: it uses an "ignore duplicates" insert and checks the affected-row count (1 = applied, 0 = already inserted by another process) instead of a non-atomic check-then-insert that could raise a unique-constraint violation when two runners marked the same migration at once
- **hectororm/migration**: `MigrationRunner::down()` now reverts migrations in their actual application order (as recorded by the tracker), newest first, instead of the reverse provider order. This fixes incorrect rollback order when migrations were applied out of order. Applied migrations no longer resolvable by the provider are skipped.
- **hectororm/migration**: `DbTracker` operations (`isApplied`, `markApplied`, `markReverted`, iteration) no longer break when the tracking table name needs quoting (e.g. a reserved word such as `order`); previously only `createTable()` quoted it
- **hectororm/orm**: Fix `OrmFactory::orm()` fataling with `Call to a member function set() on null` when called without a cache (the default): the cache write is now nullsafe (`$cache?->set(...)`), matching the already-nullsafe `$cache?->get(...)`, so the factory is usable without a PSR-16 cache
- **hectororm/orm**: Fix `#[Type]` precedence in `ReflectionEntity`: a `#[Type('col', ...)]` declared in a subclass was overwritten by the same column declared in a parent class (the hierarchy walk unconditionally reassigned the column key). The child now wins (first-write-wins), consistent with how `#[Mapper]` and `#[Table]` resolution already stop at the first match
- **hectororm/orm**: Fix `Entity::load()` fataling with `Call to a member function load() on null` when a nested relation is requested on a `ManyToOne`/`HasOne` that resolves to `null` (e.g. `$film->load(['original_language' => ['films']])` when `original_language_id` is `NULL`): the nested load is now nullsafe and is skipped when the relation is null
- **hectororm/orm**: Fix silently losing a modified primary key on `update`: changing a primary-key value on a loaded entity and saving it used to build the `UPDATE` `WHERE` clause from the new (non-existent) key while stripping the key from the `SET` clause, so no row was updated and the entity was silently reverted to its original key on refresh. `AbstractMapper::updateEntity()` now throws a `MapperException` when the primary key of a loaded entity has been mutated
- **hectororm/orm**: `Relationship\ManyToOne::valid()` now accepts a subclass of the target entity (`instanceof`) instead of requiring the exact class, so a related entity that extends the target is no longer rejected
- **hectororm/orm**: `Mapper\AbstractMapper::refreshEntity()` no longer falls back to a condition built from all the entity's columns (or an unconditioned `SELECT`) when no primary value is available: it now throws a clear `MapperException` instead of risking hydrating an arbitrary row. The not-found message typo ("unexciting") is fixed to "non-existing"
- **hectororm/orm**: Fix `OneToMany::linkNative()` not clearing detached entities: after deleting the detached children it now calls `$foreign->clearDetached()` (as `ManyToMany::linkNative()` already does). Previously a second save of the parent re-iterated the already-deleted children and threw `OrmException('Entity does not exists in storage')`
- **hectororm/orm**: Fix `Entity::isEqualTo()` and `OneToMany::linkNative()` dropping falsy primary-/foreign-key values (`0`, `'0'`): `array_filter()` is now called with a `null`-only callback so an entity (or relation) whose key is `0` is no longer treated as having no key. `isEqualTo()` now filters both compared primary-key arrays identically (previously only the left side was filtered, breaking comparison of composite keys containing a falsy value). This also fixes `Collection::contains()` for such entities
- **hectororm/orm**: Fix `ManyToMany::linkNative()` doubling the in-memory related collection on every `save()`: when the relation was already loaded, `Related::get()` returned the very same collection instance that was passed in as `$foreign`, so appending it onto itself duplicated its content (and triggered a redundant pivot `EXISTS`/`UPDATE` per duplicate). The append loop is now skipped when the two are the same instance, and otherwise only adds entities not already contained
- **hectororm/orm**: Fix `ManyToMany::linkNative()` losing pivot foreign keys and never persisting pivot data: pivot keys (foreign keys, from `getPivotData()`) and additional pivot data (`getPivot()->getData()`) are now handled separately. Previously, when the foreign entity carried a `PivotData`, the resolved foreign keys were overwritten by the (possibly empty) extra data — causing an unjustified `RelationException` when re-attaching an already-loaded entity (empty `getData()`), an `INSERT` without the foreign-key columns when the extra data was non-empty, and an `UPDATE` that rewrote the keys to themselves and never persisted pivot-data changes. The `INSERT` now merges keys and extra data, and the `UPDATE` writes only the additional data (skipped when there is none)
- **hectororm/orm**: Fix `RegularRelationship::getBuilder()` and `ManyToMany::getBuilder()` emitting an invalid `IN (  )` clause when the source key-set is empty (e.g. an entity whose foreign key is `NULL`, such as `film.original_language_id`): both now check the resolved key values and return an unfiltered builder when there are none, instead of guarding only on the entity count. `RegularRelationship::get()` now also computes its emptiness guard on the filtered entities (consistent with the builder it then calls)
- **hectororm/orm**: Fix loose `==`/`!=` key-tuple comparison during eager-loading association (`ManyToOne`, `OneToOne`, `OneToMany`, `ManyToMany`): a new normalized per-key comparison keeps the int/string tolerance (e.g. `5` and `"5"`) but no longer coerces numeric-looking strings (`"01"` vs `"1"`, `"1e2"` vs `"100"`) nor conflates `null` with `0`, preventing wrong associations on string-typed numeric keys
- **hectororm/orm**: Fix `Orm::persist()` failing on any pending entity: `EntityStorage::getIterator()` wrapped the underlying `WeakMap` in an `IteratorIterator`, which yields the statuses (map values) instead of the entities, so `persist()` passed integers to `persistEntity()` and always threw `OrmException('Error while persisting entities')`. Iteration now yields the `Entity` instances.
- **hectororm/orm**: Fix `Orm::persist()` calling `beginTransaction()`/`commit()`/`rollBack()` on `ConnectionSet`, which did not implement them (fatal `Error`); the transaction now spans every connection of the set
- **hectororm/orm**: Fix `SQLSTATE[HY000] 3065` in optimized pagination (`Builder::paginate(..., optimized: true)`) when ordering by a non-primary-key column: ORDER BY column references are now added to the `SELECT DISTINCT` id subquery (SQL-standard, portable across MySQL/MariaDB/PostgreSQL/SQLite). SQL expressions such as `RAND()` are intentionally not mirrored, to preserve de-duplication.
- **hectororm/orm**: Fix inflated total in optimized pagination (`Builder::paginate(..., optimized: true, withTotal: true)`) when the query has a duplicating JOIN: the total now counts distinct primary keys (via the same `SELECT DISTINCT` subquery used to fetch items) instead of the JOIN-inflated row count
- **hectororm/orm**: Fix `Undefined array key` warning in `AbstractMapper::getEntityAlteration()` when the entity's stored original data does not contain all checked columns (e.g. after a partial-column fetch): missing columns are now reported as altered instead of emitting a warning
- **hectororm/orm**: Fix `Builder::findOrFail()` not throwing `NotFoundException` when called with several primary keys that all match nothing: `find()` returns an empty `Collection` in that case, and `empty()` is always false on an object, so the emptiness is now checked explicitly
- **hectororm/orm**: Fix `MagicEntity` magic accessors blocking `#[Hidden]` columns: `__isset()` no longer hides them, so reading, writing and `isset()` work again (hidden becomes an output filter only, aligned with Eloquent/Doctrine)
- **hectororm/pagination**: A pagination built from a generator can now be iterated after `count()`, `isEmpty()`, `getArrayCopy()` or `jsonSerialize()` materialised it: `getIterator()` serves the cached items instead of returning the exhausted generator (which raised "Cannot rewind/traverse an already closed generator")
- **hectororm/pagination**: `OffsetPaginationNavigator::getLastRequest()` no longer returns a request for page `0` when the total is `0` (an empty result set): it returns `null`, consistently with `RangePaginationNavigator`. This previously produced a `Link rel="last"` to `page=0` (an invalid page yielding a negative offset)
- **hectororm/pagination**: `OffsetPaginator`, `CursorPaginator` and `RangePaginator` now validate their per-page/limit configuration in the constructor (`defaultPerPage`/`defaultLimit` must be `>= 1`, and `maxPerPage`/`maxLimit` must be `>= 1` or `false`), throwing `InvalidArgumentException` instead of silently producing a `LIMIT 0`
- **hectororm/pagination**: `PaginationView` no longer reports `end < start` for an empty page: `start`/`end` are `null` when the page has no items, instead of `start = offset` and `end = offset - 1` (e.g. `0` / `-1`)
- **hectororm/pagination**: `CacheCursorStorage::store()` now throws a `RuntimeException` when the underlying PSR-16 cache `set()` returns `false`, instead of returning a cursor name that was never stored (and would be unresolvable later)
- **hectororm/query**: `Insert::ignore()` / `QueryBuilder::ignore()` now emit driver-specific "ignore duplicates" syntax instead of always producing the MySQL-only `INSERT IGNORE`, which raised a syntax error on SQLite and PostgreSQL: SQLite gets `INSERT OR IGNORE`, PostgreSQL gets the `ON CONFLICT DO NOTHING` suffix, and MySQL/MariaDB (or an unknown/absent driver) keep `INSERT IGNORE`
- **hectororm/query**: `Order::random()` now emits the driver-specific random function instead of a hardcoded `RAND()` (invalid on SQLite/PostgreSQL, which use `RANDOM()`); the function is resolved at render time from `DriverInfo`
- **hectororm/query**: `whereIn`/`whereNotIn`/`havingIn`/`havingNotIn` (and the `IN` auto-detected by `Conditions::equal()`) with an empty list no longer emit an invalid `IN (  )` clause: `IN []` now renders the always-false `1 = 0` and `NOT IN []` the always-true `1 = 1`, preserving the correct set semantics across drivers
- **hectororm/query**: `QueryBuilder` locking (`fetchOne`/`fetchAll`/`fetchColumn` with `lock: true`) now emits a plain `FOR UPDATE` on drivers that lock rows but do not support `SKIP LOCKED` (e.g. MySQL < 8.0), instead of silently emitting no lock clause at all
- **hectororm/query**: `Statement\Quoted` now drops empty segments (leading/trailing/double dots, empty identifier) instead of emitting invalid SQL like `` `a`.`b`. `` or an empty string; an all-empty identifier returns `null`
- **hectororm/schema**: `Index::getColumns()` no longer throws `TypeError` on multi-column indexes (e.g. composite primary keys): the ordering comparator used `strcmp()` on the integer column positions, which fails under `strict_types`; it now uses the `<=>` operator
- **hectororm/schema**: Keep the numeric precision of a SQLite column type that has no scale (e.g. `DECIMAL(10)`, `INT(11)`): the precision was driven by the presence of a *scale*, so a precision-only type ended up with `numeric_precision = null` and the size was dropped on table rebuilds. Precision is now driven by the size, exclusively for numeric (non-string) types
- **hectororm/schema**: Parse SQLite column types with a MySQL-style trailing `unsigned` keyword (e.g. `int(10) unsigned`): the type was matched right-anchored, which captured only the trailing `unsigned` word, yielding the bogus type name `unsigned`, a lost size and `unsigned = false`. The type, size and `unsigned` flag are now parsed regardless of the keyword's position
- **hectororm/schema**: `ForeignKey::getReferencedTable()` now returns `null` instead of raising a `Call to a member function on null` error when the schema or its container cannot be resolved: the nullsafe operator was applied only to the first link of the chain and not propagated to `getSchema()`/`getTable()`
- **hectororm/schema**: Report every column of a composite primary key as not nullable on SQLite: `PRAGMA table_info` exposes `pk` as the 1-based position in the primary key, and the nullability check compared it to `1`, so the 2nd, 3rd… columns of a composite primary key were wrongly marked nullable
- **hectororm/schema**: Scope MySQL foreign-key introspection to the constraint schema: the join between `key_column_usage` and `referential_constraints` matched on the constraint name only, which is unique per schema, so another database holding a same-named foreign key produced a cartesian product (duplicated columns and `UPDATE`/`DELETE` rules read from the wrong database). The join now also matches `constraint_schema`
- **hectororm/schema**: Report a zero numeric scale as `0` instead of `null` on MySQL: `NUMERIC_SCALE` is falsy for integers and `DECIMAL(x,0)`, and a truthy check turned it into `null`, so `getNumericScale()` lost the distinction between "no scale" and "scale 0" (e.g. dropping the `,0` when re-compiling a column definition)
- **hectororm/schema**: Column default values that are SQL expressions (e.g. `CURRENT_TIMESTAMP()`) were quoted as string literals, producing invalid DDL such as `DEFAULT 'CURRENT_TIMESTAMP()'`; wrap them in `Hector\Schema\Plan\Raw` to emit them verbatim
- **hectororm/schema**: Preserve numeric precision/scale (e.g. `DECIMAL(10,2)`) on SQLite table rebuilds: columns carried over unchanged were reconstructed using only the string length, dropping precision/scale (`DECIMAL(10,2)` became `decimal`)
- **hectororm/schema**: Preserve the `INTEGER PRIMARY KEY` and its `AUTOINCREMENT` on SQLite table rebuilds: the generator now introspects the rowid primary key from `PRAGMA table_info` (it never appears in `PRAGMA index_list`) and detects `AUTOINCREMENT` on quoted identifiers, and the compiler emits `INTEGER` (not the `int` synonym, which SQLite rejects) for autoincrement columns. Previously both were silently dropped, corrupting the table on `modifyColumn`/rebuild

### Security

- Bump minimum phpunit version to ^9.6.34
- **hectororm/connection**: Mask `user`/`password`/`pwd` parameters embedded in the DSN before logging connection entries
- **hectororm/connection**: Convert PDO connection failures into a `ConnectionException` without secrets, and mark the `username`/`password` constructor arguments with `#[\SensitiveParameter]` to keep them out of stack traces on PHP < 8.2
- **hectororm/migration**: `DbTracker` now quotes the tracking table name in all its DML queries (via `Query\Statement\Quoted`) instead of interpolating it verbatim, removing the SQL injection surface on a dynamically configured `tableName`
- **hectororm/orm**: `MagicEntity::jsonSerialize()` and `MagicEntity::__debugInfo()` no longer expose columns declared with `#[Hidden]`, preventing leakage of secrets (passwords, tokens) through `json_encode()` or dumps
- **hectororm/pagination**: `OffsetPaginationRequest::fromRequest()` now bounds `page` from above so a hostile `?page=PHP_INT_MAX` no longer overflows `getOffset()` into a float and throws an uncaught `TypeError` (HTTP 500)
- **hectororm/pagination**: `RangePaginationRequest::fromRequest()` / `fromHeader()` now normalize a reversed range (e.g. `range=20-10`) so `getLimit()` can no longer become negative and be injected as an invalid SQL `LIMIT`
- **hectororm/pagination**: `CursorPaginationRequest::fromRequest()` now treats a non-string `cursor` parameter (e.g. `?cursor[]=x`) as no cursor instead of passing an array to `fromCursor(?string)` and throwing an uncaught `TypeError` (HTTP 500)

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
