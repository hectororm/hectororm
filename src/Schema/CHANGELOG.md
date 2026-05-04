# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
