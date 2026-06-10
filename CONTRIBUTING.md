# Contributing to Hector ORM

First off, thank you for considering contributing to Hector ORM! Every contribution helps make Hector ORM better
for everyone.

## Monorepo

This repository is a **monorepo** that contains all official Hector ORM packages. Sub-repositories (e.g.
`hectororm/orm`, `hectororm/query`, etc.) are **read-only mirrors** that are synchronized automatically.

**All contributions (bug reports, feature requests, pull requests) must be made on this repository.**

## Reporting bugs

Before opening a new issue, please check if a similar issue already exists.

When reporting a bug, please include:

- The PHP version you are using.
- The Hector ORM version (or commit hash).
- The affected package (e.g. `hectororm/orm`, `hectororm/query`).
- The database engine and version when relevant (MySQL, MariaDB, SQLite).
- A clear description of the expected behavior and what actually happened.
- Steps to reproduce the issue, ideally with a minimal code example.

## Suggesting features

Feature requests are welcome. Open an issue with the `enhancement` label and describe:

- The problem you are trying to solve.
- How you envision the solution.
- Any alternatives you have considered.

## Pull requests

### Getting started

1. **Fork** the repository and clone your fork locally.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Create a **new branch** from `main`:
   ```bash
   git checkout -b my-feature
   ```

### Coding standards

- PHP code must follow the **PSR-12 / PER** coding style.
- Use `declare(strict_types=1);` in every PHP file.
- Indentation: 4 spaces for PHP files, 2 spaces for other files (see `.editorconfig`); LF line endings.
- Maximum line length: 120 characters for PHP files.
- Import classes with `use` statements; do not use fully qualified names in code.
- Use **Yoda conditions** (`null === $var`, not `$var === null`).
- Prefer **guard clauses** (early `return`/`continue`) over deeply nested `if` blocks.
- Collections implement `IteratorAggregate` + `Countable` and provide `getArrayCopy(): array`.

### Tests

All changes must be covered by tests. The project uses **PHPUnit 9.6**, and `tests/` mirrors the `src/` structure.

SQLite tests require `ext-pdo_sqlite` and run out of the box. MySQL/MariaDB tests require `ext-pdo_mysql` and a
running server with the **Sakila sample database** loaded:

```bash
mysql -uroot < tests/Assets/Sakila/mysql-schema.sql
mysql -uroot < tests/Assets/Sakila/mysql-data.sql
```

The default DSN is `mysql:host=localhost;dbname=sakila;user=root` and can be overridden with the `MYSQL_DSN`
environment variable.

```bash
# Run the full test suite
vendor/bin/phpunit

# Run tests for a specific package
vendor/bin/phpunit tests/Query

# Run a single test class
vendor/bin/phpunit --filter ClassName
```

Make sure all tests pass before submitting your pull request:

```bash
vendor/bin/phpunit --coverage-text
```

### Static analysis

The project uses **Rector** for automated code quality checks:

```bash
# Check for issues (dry run)
vendor/bin/rector --dry-run

# Apply fixes
vendor/bin/rector
```

### Changelog

Each package keeps its own changelog. When adding a feature or fixing a bug, update the `[Unreleased]` section of
the relevant `src/<Package>/CHANGELOG.md`. The root `CHANGELOG.md` is auto-generated at release time and must not
be edited manually.

### Commit messages

- Use clear, descriptive commit messages.
- Start with a short summary (max 72 characters).
- Use the imperative mood ("Add feature", not "Added feature").
- Reference related issues when applicable (e.g. `Fix #42`).

### Submitting

1. Make sure the test suite passes and Rector reports no changes.
2. Update the relevant `src/<Package>/CHANGELOG.md` `[Unreleased]` section.
3. Push your branch to your fork.
4. Open a **pull request** against the `main` branch of this repository.
5. Describe your changes clearly in the pull request description.
6. Be prepared to address feedback during code review.

## Development setup

### Requirements

- PHP >= 8.0
- Extensions: `mbstring`, `pdo`
- Composer v2

### Optional extensions

Some packages require additional extensions for their full test suite:

- `pdo_sqlite` -- SQLite tests (always needed)
- `pdo_mysql` -- MySQL / MariaDB tests against the Sakila database
- `sodium` -- Encrypted cursor pagination in `hectororm/pagination`

## Code of conduct

Please be respectful and constructive in all interactions. We are committed to providing a welcoming and inclusive
experience for everyone. This project follows the organization-wide
[Code of Conduct](https://github.com/hectororm/.github/blob/main/CODE_OF_CONDUCT.md).

For security issues, please refer to our
[Security Policy](https://github.com/hectororm/.github/blob/main/SECURITY.md) instead of opening a public issue.

## Questions?

If you have any questions, feel free to open an issue or reach out via the project website at
[gethectororm.com](https://gethectororm.com).
