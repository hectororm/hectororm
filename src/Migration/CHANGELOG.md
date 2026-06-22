# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `FileTracker` now writes its JSON tracking file atomically (write to a temporary file in the same directory, then `rename()` over the target) and detects partial writes (bytes written must match the payload), so a crash mid-write can no longer truncate or corrupt the tracking file (the source of truth)
- `MigrationRunner::down()` now reverts migrations in their actual application order (as recorded by the tracker), newest first, instead of the reverse provider order. This fixes incorrect rollback order when migrations were applied out of order. Applied migrations no longer resolvable by the provider are skipped.

## [1.3.0] - 2026-05-12

Initial release.
