# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `LogEntry::__construct()` now requires a non-null `string $statement` (was `?string`): a log entry always represents a statement, and `getStatement(): string` previously threw a `TypeError` when the entry was built with `null`. The constructor now rejects `null` outright. No production caller is affected (`Logger::newEntry()` already typed `$statement` as non-null)
- `ConnectionSet::addConnection()` now throws a `ConnectionException` when a connection with the same name is already registered, instead of silently overwriting (and discarding) the previous one, which is a configuration error
### Security

- Mask `user`/`password`/`pwd` parameters embedded in the DSN before logging connection entries
- Convert PDO connection failures into a `ConnectionException` without secrets, and mark the `username`/`password` constructor arguments with `#[\SensitiveParameter]` to keep them out of stack traces on PHP < 8.2
### Fixed

- Fix the transaction counter desyncing when `beginTransaction()` fails: the counter is now incremented only after `PDO::beginTransaction()` succeeds (previously a failure left the counter incremented with no real transaction, permanently routing reads to the write PDO and making the next `commit()` call `PDO::commit()` without an active transaction). Nesting semantics are preserved (only the outermost call touches the real PDO)
### Fixed

- Fix `Connection::yieldColumn()` truncating the result set when a column value is boolean `false`: it now traverses the statement in `PDO::FETCH_COLUMN` mode instead of looping on `false !== fetchColumn()`, which conflated a `false` value with end-of-cursor (observable with PostgreSQL boolean columns)

## [1.3.0] - 2026-05-12

### Added

- Added `DriverInfo::getIdentifierQuote()` method to expose the driver-specific identifier quoting character
- Added `DriverCapabilities::hasRenameColumn()` to detect support for `RENAME COLUMN` syntax (false on MySQL < 8.0)

### Fixed

- Use `PDO::PARAM_NULL` for `null` values in `BindParam::findDataType()` instead of `PDO::PARAM_STR`

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
