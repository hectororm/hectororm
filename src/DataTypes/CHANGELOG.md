# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `AbstractType::assertNullable()` helper to validate that a `null` value is permitted by the expected type (always allowed when no `ExpectedType` is provided)
- `TypeSet` now maps the `time`, `bit`, `binary` and `varbinary` MySQL column types (previously they threw `UnexpectedValueException`)

### Changed

- `AbstractType::equals()` (the dirty-checking base used by most types) no longer compares with loose `==`. `null` is equal only to `null`, and scalars are compared by their string form, so real changes previously hidden (e.g. `"1e3"` vs `"1000"`, `"1.0"` vs `"1"`, `null` vs `""`) are now detected and persisted, while the legitimate int/float vs numeric-string juggling between the entity and the database value (e.g. `1` vs `"1"`) still compares as equal
- All concrete types now handle `null` consistently: `fromSchema(null)` returns `null` when the expected type allows it (or when no expected type is given) and throws `ValueException` otherwise; `toSchema(null)` returns `null` so nullable columns persist as SQL `NULL`. The return types of `BooleanType::toSchema()` (`?int`) and `StringType`/`DateTimeType`/`UuidType`/`RamseyUuidType`/`SetType` `toSchema()` (`?string`) were widened accordingly

### Fixed

- `StringType::fromSchema()` now resolves backed-enum typed columns: the `is_a()` class-string check was missing its third `$allow_string` argument, so the `BackedEnum` branch was never reached and valid enum values were rejected with a "not builtin" error
- `JsonType::fromSchema()` null handling is now reachable (the `assertScalar()` call previously rejected `null` before the null branch could run)
- `JsonType::equals()` now compares both sides canonically (recursive key sorting) so object/associative-array values with a different key order are considered equal, avoiding spurious `UPDATE` queries; it also no longer triggers a `TypeError` when the schema side is already a decoded array
- `UuidType::fromSchema()` no longer triggers a `strlen(null)` deprecation and casts the value to string before length checks
- `SetType::equals()` now treats an empty string and an empty array as the same empty set (empty members are stripped), avoiding a spurious "not equal" result that triggered unnecessary `UPDATE` queries
- `JsonType::equals()` no longer uses `empty()` against a NULL column, which wrongly treated meaningful falsy JSON values (`0`, `0.0`, `false`, `[]`, `'0'`) as unchanged so they were never persisted; only an empty JSON payload (empty string) is now considered equal to a NULL column

## [1.3.0] - 2026-05-12

### Added

- `RamseyUuidType`: throw `LogicException` with install hint when `ramsey/uuid` is not installed

### Fixed

- Fix missing `throw` in `EnumType::fromSchema()` when expected type does not match configured enum class
- Fix inverted type check in `RamseyUuidType::fromSchema()` that rejected valid `UuidInterface` properties
- Fix `DefaultType::toSchema()` return type from `string` to `mixed` to match interface and actual behavior
- Fix `JsonType::equals()` comparing decoded entity data with JSON string, causing unnecessary UPDATE queries
- Fix typo in `TypeSet`: `longblog` renamed to `longblob`
- Fix `SetType::equals()` crashing with `TypeError` when entity data is an array

## [1.2.2] - 2026-02-05

_No changes in this release._

## [1.2.1] - 2026-01-13

_No changes in this release._

## [1.2.0] - 2026-01-13

### Fixed

- Argument `$type` renamed to `$numericType` in `NumericType` constructor

## [1.1.0] - 2025-11-21

### Changed

- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
