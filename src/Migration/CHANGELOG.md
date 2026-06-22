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
- `MigrationRunner::down()` now reverts migrations in their actual application order (as recorded by the tracker), newest first, instead of the reverse provider order. This fixes incorrect rollback order when migrations were applied out of order. Applied migrations no longer resolvable by the provider are skipped.
- `DbTracker` operations (`isApplied`, `markApplied`, `markReverted`, iteration) no longer break when the tracking table name needs quoting (e.g. a reserved word such as `order`); previously only `createTable()` quoted it

## [1.3.0] - 2026-05-12

Initial release.
