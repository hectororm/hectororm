# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- `OffsetPaginationRequest::fromRequest()` now bounds `page` from above so a hostile `?page=PHP_INT_MAX` no longer overflows `getOffset()` into a float and throws an uncaught `TypeError` (HTTP 500)
- `RangePaginationRequest::fromRequest()` / `fromHeader()` now normalize a reversed range (e.g. `range=20-10`) so `getLimit()` can no longer become negative and be injected as an invalid SQL `LIMIT`
- `CursorPaginationRequest::fromRequest()` now treats a non-string `cursor` parameter (e.g. `?cursor[]=x`) as no cursor instead of passing an array to `fromCursor(?string)` and throwing an uncaught `TypeError` (HTTP 500)

### Changed

- The `OffsetPaginationRequest` and `RangePaginationRequest` constructors now throw `InvalidArgumentException` on invalid values (`page`/`perPage` < 1, `start` < 0, `end` < `start`), consistent with the `OffsetPagination` and `RangePagination` model classes. The `fromRequest()`/`fromHeader()` factories keep clamping untrusted HTTP input silently and never throw.
### Fixed

- A pagination built from a generator can now be iterated after `count()`, `isEmpty()`, `getArrayCopy()` or `jsonSerialize()` materialised it: `getIterator()` serves the cached items instead of returning the exhausted generator (which raised "Cannot rewind/traverse an already closed generator")
### Fixed

- `OffsetPaginationNavigator::getLastRequest()` no longer returns a request for page `0` when the total is `0` (an empty result set): it returns `null`, consistently with `RangePaginationNavigator`. This previously produced a `Link rel="last"` to `page=0` (an invalid page yielding a negative offset)
### Fixed

- `OffsetPaginator`, `CursorPaginator` and `RangePaginator` now validate their per-page/limit configuration in the constructor (`defaultPerPage`/`defaultLimit` must be `>= 1`, and `maxPerPage`/`maxLimit` must be `>= 1` or `false`), throwing `InvalidArgumentException` instead of silently producing a `LIMIT 0`
- `PaginationView` no longer reports `end < start` for an empty page: `start`/`end` are `null` when the page has no items, instead of `start = offset` and `end = offset - 1` (e.g. `0` / `-1`)
- `CacheCursorStorage::store()` now throws a `RuntimeException` when the underlying PSR-16 cache `set()` returns `false`, instead of returning a cursor name that was never stored (and would be unresolvable later)

## [1.3.0] - 2026-05-12

Initial release.
