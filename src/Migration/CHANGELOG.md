# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- `DbTracker` now quotes the tracking table name in all its DML queries (via `Query\Statement\Quoted`) instead of interpolating it verbatim, removing the SQL injection surface on a dynamically configured `tableName`

### Fixed

- `FileTracker` now writes its JSON tracking file atomically (write to a temporary file in the same directory, then `rename()` over the target) and detects partial writes (bytes written must match the payload), so a crash mid-write can no longer truncate or corrupt the tracking file (the source of truth)
- `DirectoryProvider` now orders recursively-scanned migrations by file name instead of by absolute path, so a timestamp-prefixed migration in a subdirectory is no longer mis-ordered relative to one at the root (sorting full paths interleaved directories, e.g. `20260301_Root.php` sorted before `sub/20260101_Nested.php`). `Psr4Provider` keeps ordering by fully-qualified class name (it has no timestamp-in-filename convention); the file sort key is exposed via a protected `sortKey()` hook
- Directory/PSR-4 migration providers now reject a non-instantiable migration class (e.g. an abstract class that implements `MigrationInterface`) with a clear `MigrationException` instead of letting `new` raise a raw `Error` (the class is checked with `ReflectionClass::isInstantiable()` before direct construction; classes built by the PSR-11 container are left to the container)
- `DbTracker::markReverted()` is now idempotent: deleting a migration it does not track is a no-op instead of throwing. This stops a `ChainTracker` from breaking mid-way (leaving trackers out of sync) when reverting a migration that only some trackers recorded
- `ChainTracker` now validates its strategy in the constructor (throwing `MigrationException` on an unknown value) instead of only failing later on the first read operation
- `MigrationRunner` now runs a migration's own `up()`/`down()` (the user code that builds the `Plan`) inside the failure handling: an exception thrown while building the plan is logged, dispatched as a `MigrationFailedEvent` and wrapped in a `MigrationException` (with the original as `previous`), like an execution failure — previously it escaped raw. A failing `rollBack()` in the error path no longer masks the original exception, and a rollback is attempted only when a transaction was actually started
### Changed

- **BREAKING:** the `DbTracker` tracking table now has a mandatory `seq` column (a monotonic, insertion-ordered sequence) used to order applied migrations. Tables created by a previous version must be migrated manually, e.g. `ALTER TABLE hector_migrations ADD COLUMN seq BIGINT NOT NULL DEFAULT 0` followed by backfilling `seq` in application order. Without it, `DbTracker` will fail to read/write the tracking table
- `DbTracker` now stores `applied_at` in UTC (`gmdate()`) instead of the local timezone
- `MigrationRunner` now records the tracking write inside the migration transaction when the driver supports transactional DDL (SQLite/PostgreSQL), so a migration and its tracking row commit or roll back atomically

### Fixed

- `MigrationRunner` no longer leaves the database in an "applied but untracked" state when tracking fails: on transactional-DDL drivers the whole migration is rolled back, and on other drivers a tracking failure now raises a `MigrationException` and dispatches a `MigrationFailedEvent` instead of throwing silently outside the error path
- `DbTracker` now replays migrations in their exact application order via the new `seq` column. Previously it ordered by `applied_at` (second precision) then `migration_id` (alphabetical), so migrations applied within the same second were re-ordered alphabetically, causing `down()` to revert them in the wrong order
- `MigrationRunner::down()` now reverts migrations in their actual application order (as recorded by the tracker), newest first, instead of the reverse provider order. This fixes incorrect rollback order when migrations were applied out of order. Applied migrations no longer resolvable by the provider are skipped.
- `DbTracker` operations (`isApplied`, `markApplied`, `markReverted`, iteration) no longer break when the tracking table name needs quoting (e.g. a reserved word such as `order`); previously only `createTable()` quoted it

## [1.3.0] - 2026-05-12

Initial release.
