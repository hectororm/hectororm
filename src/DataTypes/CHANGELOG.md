# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2026-06-30

### Added

- `AbstractType::assertNullable()` helper to validate that a `null` value is permitted by the expected type (always allowed when no `ExpectedType` is provided)
- `TypeSet` now maps the `time`, `bit`, `binary` and `varbinary` MySQL column types (previously they threw `UnexpectedValueException`)
- New `DecimalType` for exact `DECIMAL`/`NUMERIC` columns: it keeps the value as its canonical numeric string instead of going through a lossy PHP `float`, and hydrates a `\BcMath\Number` (PHP 8.4+) when the entity property is typed as such. `TypeSet` now maps `decimal` and `numeric` to this type automatically (the approximate `float`/`double` types are unchanged), preventing precision loss on high-precision values such as monetary amounts

### Changed

- `AbstractType::equals()` (the dirty-checking base used by most types) no longer compares with loose `==`. `null` is equal only to `null`, and scalars are compared by their string form, so real changes previously hidden (e.g. `"1e3"` vs `"1000"`, `"1.0"` vs `"1"`, `null` vs `""`) are now detected and persisted, while the legitimate int/float vs numeric-string juggling between the entity and the database value (e.g. `1` vs `"1"`) still compares as equal
- All concrete types now handle `null` consistently: `fromSchema(null)` returns `null` when the expected type allows it (or when no expected type is given) and throws `ValueException` otherwise; `toSchema(null)` returns `null` so nullable columns persist as SQL `NULL`. The return types of `BooleanType::toSchema()` (`?int`) and `StringType`/`DateTimeType`/`UuidType`/`RamseyUuidType`/`SetType` `toSchema()` (`?string`) were widened accordingly

### Fixed

- `StringType::fromSchema()` now resolves backed-enum typed columns: the `is_a()` class-string check was missing its third `$allow_string` argument, so the `BackedEnum` branch was never reached and valid enum values were rejected with a "not builtin" error
- `StringType::toSchema()` now serializes a `BackedEnum` to its backing value, making the round-trip symmetric with `fromSchema()`; previously an entity with a backed-enum property could be hydrated but not persisted (the enum was neither scalar nor `Stringable`, so it raised a "cast" error)
- `StringType::fromSchema()` now hydrates int-backed enums from the numeric strings returned by PDO (the value is coerced to the enum backing type) and wraps the native `TypeError`/`ValueError` raised by `Enum::from()` in a `ValueException`; previously an int-backed enum could never be hydrated from MySQL (raw `TypeError`) and an unknown enum value leaked a raw `ValueError`
- `DateTimeType::fromSchema()` now raises a `ValueException` when an invalid date is requested as an `int`, instead of silently casting `strtotime()`'s `false` to `0` (1970-01-01)
- `JsonType::fromSchema()` null handling is now reachable (the `assertScalar()` call previously rejected `null` before the null branch could run)
- `JsonType::fromSchema()` with a `stdClass`-typed property now uses `JSON_THROW_ON_ERROR` instead of the misplaced encode-only `JSON_FORCE_OBJECT` flag (which `json_decode` ignored): invalid JSON now raises a `ValueException` instead of returning `null` silently, and the decoded value is cast to `stdClass` so a JSON array or scalar always yields an object instead of a PHP array/scalar
- `JsonType::equals()` now compares both sides canonically (recursive key sorting) so object/associative-array values with a different key order are considered equal, avoiding spurious `UPDATE` queries; it also no longer triggers a `TypeError` when the schema side is already a decoded array
- `UuidType::fromSchema()` no longer triggers a `strlen(null)` deprecation and casts the value to string before length checks
- `UuidType` now validates the value as a 16-byte binary string, a 32-character hexadecimal string or a 36-character dashed UUID before formatting; a malformed value now raises a `ValueException` instead of being returned unchanged (`fromSchema()`), emitting a `hex2bin()` warning and returning `false` (`toSchema()` binary), or raising a raw `vsprintf()` "must contain 8 items" error (`toSchema()` string)
- `SetType::equals()` now treats an empty string and an empty array as the same empty set (empty members are stripped), avoiding a spurious "not equal" result that triggered unnecessary `UPDATE` queries
- `JsonType::equals()` no longer uses `empty()` against a NULL column, which wrongly treated meaningful falsy JSON values (`0`, `0.0`, `false`, `[]`, `'0'`) as unchanged so they were never persisted; only an empty JSON payload (empty string) is now considered equal to a NULL column
- `DateTimeType` now applies a single, consistent timezone to every conversion path. Previously the numeric/timestamp path (`@timestamp`) rendered UTC wall-clock while the string and object paths used the ambient PHP timezone, so the same instant produced different values and round-trips could shift. An optional `$timezone` constructor argument has been added (defaulting to `null`, i.e. the ambient `date_default_timezone_get()`, which preserves the previous string-path behaviour while making the numeric path agree with it)
- `NumericType` now rejects non-numeric strings with a `ValueException` instead of silently coercing them to `0`/`0.0` through `settype()` (e.g. `fromSchema('abc')` previously returned `0`). Booleans and already-numeric values are unaffected, and the legitimate `float`-to-`int` truncation driven by the configured numeric type is preserved
- `BooleanType` now resolves the textual values `"true"`/`"false"` before casting, so a column holding the string `"false"` is no longer interpreted as `true` when the entity property is typed `bool`/`int` (the textual normalization previously applied only when no expected type was set, and `settype((bool) "false")` yields `true`)
- `EnumType` now hydrates int-backed enums from the numeric strings returned by PDO (the value is coerced to the enum backing type), so an int-backed enum is no longer impossible to read from MySQL (raw `TypeError`); it also wraps the native `TypeError`/`ValueError` raised by `from()`/`toSchema()` in a `ValueException`. In "try" mode a type mismatch now yields `null` instead of a `TypeError`
- `SetType::fromSchema()` now hydrates an empty SET as an empty array instead of `['']` (a single empty member), and `SetType::toSchema()` now accepts the raw comma-separated string in addition to an array, so a SET property typed as `string` is persistable (round-trip symmetric with `fromSchema()`)

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
