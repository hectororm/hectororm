# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Collection::first()` and `Collection::last()` now correctly return `false` and `0` values instead of `null`
- `Collection::__construct()` now properly handles `Closure` arguments (invokes the closure to produce an iterable)
- `Collection::rand()` no longer crashes on empty collections

## [1.2.2] - 2026-02-05

_No changes in this release._

## [1.2.1] - 2026-01-13

_No changes in this release._

## [1.2.0] - 2026-01-13

### Added

- Method `CollectionInterface::join()` to join items with a glue string and optional final glue
- Method `CollectionInterface::groupBy()` to group items by a key or callback result

## [1.1.0] - 2025-11-21

### Changed

- Improve PHPDoc to enable IDE type inference for concrete entity classes
- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
