# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Use of the deprecated method ReflectionProperty::setAccessible() with PHP > 8.0

## [1.2.0] - 2026-01-13

### Removed

- Remove unnecessary PhpDoc template

### Fixed

- `Builder::chunk()` now respects pre-defined `limit` and `offset` constraints

## [1.1.0] - 2025-11-21

### Added

- Parameter `$cascade` to method `Entity::save()` (default to false) to persist related entities

### Changed

- `*Many` relationships also accept an `array` instead of just `Collection`
- Improve PHPDoc to enable IDE type inference for concrete entity classes
- Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

Initial release.
