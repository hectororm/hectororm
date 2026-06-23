# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Helper::isColumnReference()` to detect whether a value is a plain (possibly qualified/quoted) column reference, as opposed to an SQL expression/function, closure or sub-query. Quoted segments may contain dots/spaces, bare segments accept Unicode, and numeric literals are rejected
- `Helper::explodePath()` to split a (possibly qualified/quoted) SQL identifier path on its dot separators, ignoring dots enclosed in a matching pair of identifier quotes, with an `explode()`-like `$limit` and a configurable `$quotes` parameter (defaults to backtick and double quote; pass `''` to split unconditionally)
- `Pagination\AbstractQueryPaginator::extractColumnOrderItems()` returning the ORDER BY items that are plain column references (deterministic and materialisable)
- `Helper::unquote()` to de-quote an identifier: trims surrounding whitespace then strips a matching enclosing quote pair (undoubling the inner quote character), with a configurable set of quote characters
- `Pagination\AbstractQueryPaginator::fetchTotal()` extension point so subclasses can customise how the total is counted (e.g. count distinct primary keys instead of JOIN-inflated rows)

### Changed

- Cursor pagination now ignores ORDER BY expressions that are not column references (e.g. `ORDER BY RAND()`) instead of producing an invalid cursor navigation; if no column-based ORDER BY remains, it throws as before
- `Statement\Quoted` now splits composite identifiers with `Helper::explodePath()`, so a dot enclosed in identifier quotes (e.g. `` `a.b`.`c` ``) is no longer mistaken for a segment separator
- `Helper::trim()` second parameter is now the set of characters to strip (defaults to whitespace) instead of a single quote character; identifier de-quoting moved to the new `Helper::unquote()`. `Statement\Quoted`, the alias handling of `Component\Columns`/`Component\Join`/`Component\Table`, and `Pagination\AbstractQueryPaginator::normalizeColumnKey()` now rely on `Helper::unquote()`/`Helper::explodePath()`

### Fixed

- `Insert::ignore()` / `QueryBuilder::ignore()` now emit driver-specific "ignore duplicates" syntax instead of always producing the MySQL-only `INSERT IGNORE`, which raised a syntax error on SQLite and PostgreSQL: SQLite gets `INSERT OR IGNORE`, PostgreSQL gets the `ON CONFLICT DO NOTHING` suffix, and MySQL/MariaDB (or an unknown/absent driver) keep `INSERT IGNORE`
- `Statement\Quoted` now drops empty segments (leading/trailing/double dots, empty identifier) instead of emitting invalid SQL like `` `a`.`b`. `` or an empty string; an all-empty identifier returns `null`

## [1.3.0] - 2026-05-12

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
- Method `QueryBuilder::chunkPaginate()` to iterate through all pages, with callback `function (mixed $items, PaginationInterface $pagination)`. Honors the builder's `limit()` as a global bound across pages by adjusting the next request via `PaginationRequestInterface::withPerPage()`

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
