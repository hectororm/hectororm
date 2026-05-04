# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
