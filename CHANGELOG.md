# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2025-11-21

### Added

- Tests with MySQL 9.5 and MariaDB 12.1 on GitHub workflows
- **hectororm/orm**: Parameter `$cascade` to method `Entity::save()` (default to false) to persist related entities

### Changed

- **hectororm/collection**: Improve PHPDoc to enable IDE type inference for concrete entity classes
- **hectororm/collection**: Performed code cleanup and refactoring using Rector
- **hectororm/connection**: Performed code cleanup and refactoring using Rector
- **hectororm/datatypes**: Performed code cleanup and refactoring using Rector
- **hectororm/orm**: `*Many` relationships also accept an `array` instead of just `Collection`
- **hectororm/orm**: Improve PHPDoc to enable IDE type inference for concrete entity classes
- **hectororm/orm**: Performed code cleanup and refactoring using Rector
- **hectororm/query**: Perf: replace a loop with foreach to avoid repeated count()
- **hectororm/query**: Performed code cleanup and refactoring using Rector
- **hectororm/schema**: Performed code cleanup and refactoring using Rector

## [1.0.0] - 2025-07-02

### 🚀 First Monorepo Release

This is the first official release of HectorORM as a monorepo.

All core components are now developed, maintained, and versioned together in a single repository:

- `hectororm/collection`
- `hectororm/connection`
- `hectororm/data-types`
- `hectororm/orm`
- `hectororm/query`
- `hectororm/schema`

#### Migration Notice

Previous component versions and their individual changelogs are now unified.  
Future changes, bug fixes, and improvements will be tracked **here, in the main monorepo changelog**.

See the commit history for all legacy changes.
