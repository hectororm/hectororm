# AGENTS.md – Hector ORM

## Project Overview

**Hector ORM** is a modern, modular, and lightweight Object-Relational Mapping (ORM) framework for PHP 8.0+.

It is structured as a **monorepo** with independent, decoupled packages:

| Package                | Namespace           | Description                                  |
|------------------------|---------------------|----------------------------------------------|
| `hectororm/collection` | `Hector\Collection` | Typed collection and lazy collection classes |
| `hectororm/connection` | `Hector\Connection` | PDO wrapper, connection set, drivers, logs   |
| `hectororm/data-types` | `Hector\DataTypes`  | Type casting and expected type definitions   |
| `hectororm/orm`        | `Hector\Orm`        | Entity mapping, relationships, storage       |
| `hectororm/pagination` | `Hector\Pagination` | Pagination utilities                         |
| `hectororm/query`      | `Hector\Query`      | Query builder (Select, Insert, Update, etc.) |
| `hectororm/schema`     | `Hector\Schema`     | Schema introspection (tables, columns, FK)   |

Sub-repositories are **read-only** and auto-synced via `config.subsplit-publish.json`.

---

## Code Conventions

- **PHP Version:** 8.0 minimum (`declare(strict_types=1);` everywhere)
- **Coding Standard:** PSR-12 / PER
- **Autoloading:** PSR-4 (see `composer.json`)
- **Testing:** PHPUnit 9.6 (`tests/` mirrors `src/` structure)
- **Static Analysis:** PHPStan (level max recommended)
- **Refactoring:** Rector (`rector.php` at root)

---

## Directory Layout

```
src/
├── Collection/      # hectororm/collection
├── Connection/      # hectororm/connection
├── DataTypes/       # hectororm/data-types
├── Orm/             # hectororm/orm (main ORM logic)
├── Pagination/      # hectororm/pagination
├── Query/           # hectororm/query
└── Schema/          # hectororm/schema

tests/               # PHPUnit tests (same structure as src/)
bin/                 # Release tooling
```

---

## Key Entry Points

| Component  | Main Class(es)                                     |
|------------|----------------------------------------------------|
| ORM        | `Hector\Orm\Orm`, `Hector\Orm\OrmFactory`          |
| Query      | `Hector\Query\QueryBuilder`, `Select`, `Insert`…   |
| Connection | `Hector\Connection\Connection`, `ConnectionSet`    |
| Schema     | `Hector\Schema\SchemaContainer`, `Table`, `Column` |
| Collection | `Hector\Collection\Collection`, `LazyCollection`   |
| DataTypes  | `Hector\DataTypes\TypeSet`, `ExpectedType`         |

---

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests require `pdo_sqlite` and optionally `pdo_mysql`.

---

## Contributing

- All issues and PRs must be opened in the **monorepo** (`hectororm/hectororm`).
- Follow existing code style; run PHPStan and Rector before submitting.
- Write or update tests for any new feature or bugfix.

---

## Documentation

Full docs: [https://gethectororm.com](https://gethectororm.com)  
Source: see `HectorDocs` repository (Markdown files).
