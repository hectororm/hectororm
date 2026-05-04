# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Statement\Expression` for composing heterogeneous SQL fragments (`StatementInterface|string`) with deferred driver-aware resolution
- `Statement\Quoted` for driver-aware deferred identifier quoting (supports composite `schema.table.column` and `*` wildcard)
- `Statement\Encapsulated` wrapper class for explicit sub-expression parenthesization
- `CompoundStatementInterface` marker interface for statements (queries, grouped conditions) that should be auto-encapsulated as sub-expressions
- Tuple format `[column, value]` in `Assignments::assignments()` and `Conditions::equals()` to allow `StatementInterface` column names (e.g. `Quoted`)
- Driver-aware identifier quoting via `DriverInfo` parameter on `StatementInterface::getStatement()`
- `Helper::quote()` and `Helper::trim()` now accept a `$quote` parameter for driver-specific quote character
- `Helper::quote()` now escapes embedded quote characters by doubling them
- Method `Component\Order::getOrder()` to get defined order
- Method `QueryBuilder::paginate()` for built-in pagination support (offset, cursor, range)
- Namespace `Pagination` with `QueryOffsetPaginator`, `QueryCursorPaginator`, `QueryRangePaginator`
- Namespace `Sort` with `SortInterface`, `Sort`, `MultiSort` and `SortConfig` for type-safe, composable sorting
- Method `QueryBuilder::applySort(SortInterface)` to apply a sort object to the query builder
- Cursor position validation in `QueryCursorPaginator` (columns match, scalar values only)

### Changed

- **BREAKING:** Removed `bool $encapsulate` parameter from `StatementInterface::getStatement()` — callers needing parentheses should use `Statement\Encapsulated` wrapper instead
- `Statement\Row` now accepts `StatementInterface|string` values (was `string` only)
- `Statement\Row::getStatement()` now always returns parenthesized format `(val1, val2)`
- `Component\AbstractComponent::getSubStatement()` and `getSubStatementValue()` auto-encapsulate `CompoundStatementInterface` instances

### Removed

- `Component\EncapsulateHelperTrait` — replaced by `Statement\Encapsulated` and `CompoundStatementInterface`

### Fixed

- Escape LIKE wildcard characters (`%`, `_`, `\`) in `whereContains`, `whereStartsWith`, `whereEndsWith` and their Having equivalents

## [1.2.2] - 2026-02-05

### Fixed

- Closure binding issue in `Conditions::getStatement()` by removing unnecessary reference in foreach loop

## [1.2.1] - 2026-01-13

_No changes in this release._

## [1.2.0] - 2026-01-13

_No changes in this release._

## [1.1.0] - 2025-11-21

### Changed

- Perf: replace a loop with foreach to avoid repeated count()
- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
