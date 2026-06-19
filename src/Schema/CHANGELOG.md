# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Index::getColumns()` no longer throws `TypeError` on multi-column indexes (e.g. composite primary keys): the ordering comparator used `strcmp()` on the integer column positions, which fails under `strict_types`; it now uses the `<=>` operator
- Keep the numeric precision of a SQLite column type that has no scale (e.g. `DECIMAL(10)`, `INT(11)`): the precision was driven by the presence of a *scale*, so a precision-only type ended up with `numeric_precision = null` and the size was dropped on table rebuilds. Precision is now driven by the size, exclusively for numeric (non-string) types
- Parse SQLite column types with a MySQL-style trailing `unsigned` keyword (e.g. `int(10) unsigned`): the type was matched right-anchored, which captured only the trailing `unsigned` word, yielding the bogus type name `unsigned`, a lost size and `unsigned = false`. The type, size and `unsigned` flag are now parsed regardless of the keyword's position
- Report every column of a composite primary key as not nullable on SQLite: `PRAGMA table_info` exposes `pk` as the 1-based position in the primary key, and the nullability check compared it to `1`, so the 2nd, 3rd… columns of a composite primary key were wrongly marked nullable
- Preserve numeric precision/scale (e.g. `DECIMAL(10,2)`) on SQLite table rebuilds: columns carried over unchanged were reconstructed using only the string length, dropping precision/scale (`DECIMAL(10,2)` became `decimal`)

## [1.3.0] - 2026-05-12

### Added

- Schema Plan system for declarative schema migrations (tables, indexes, foreign keys, views)

### Deprecated

- The `$quoted` parameter on `Schema::getName()`, `Table::getName()`, `Table::getSchemaName()`, `Table::getFullName()`, `Table::getColumnsName()`, `Column::getName()`, `Column::getFullName()`, `Index::getColumnsName()`, `ForeignKey::getColumnsName()`, and `ForeignKey::getReferencedColumnsName()` is deprecated; use `Hector\Query\Statement\Quoted` for driver-aware identifier quoting instead

### Fixed

- Sanitize identifiers in SQLite schema generator to prevent SQL injection via PRAGMA statements
- PHPUnit deprecation warnings in tests (Generator passed as `$haystack`)
- Table charset detection on MariaDB 11.4.5+ by joining `information_schema.collation_character_set_applicability` on `FULL_COLLATION_NAME`

## [1.2.2] - 2026-02-05

_No changes in this release._

## [1.2.1] - 2026-01-13

_No changes in this release._

## [1.2.0] - 2026-01-13

_No changes in this release._

## [1.1.0] - 2025-11-21

### Changed

- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
