# Hector Schema

[![Latest Version](https://img.shields.io/packagist/v/hectororm/schema.svg?style=flat-square)](https://github.com/hectororm/schema/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/schema/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/schema.svg?style=flat-square)](https://github.com/hectororm/schema/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Schema** is the schema generator module of Hector ORM. Can be used independently of ORM.

📖 **[Full documentation](https://gethectororm.com/docs/current/components/schema)**

## Installation

You can install **Hector Schema** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require hectororm/schema
```

## Documentation

### Column character sets and collations

`addColumn()` and `modifyColumn()` accept optional `?string $charset = null` and
`?string $collation = null` arguments, appended after the existing arguments.
Names are native to the target database, not portable presets. Existing calls and
table-level string options keep their behavior.

For example, store canonical textual UUIDs in an ASCII column on MySQL/MariaDB,
while keeping the same migration executable on SQLite:

```php
use Hector\Schema\Plan\Plan;

$plan = new Plan();
$plan->create('documents', charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci')
    ->addColumn('id', 'CHAR(36)', charset: 'ascii')
    ->addColumn('title', 'VARCHAR(255)')
    ->addIndex('uq_documents_id', ['id'], type: 'unique');

$alter = new Plan();
$alter->alter('documents')->modifyColumn('id', 'CHAR(36)', charset: 'ascii');
```

| Option | MySQL / MariaDB | SQLite |
| --- | --- | --- |
| `charset` | Emits `CHARACTER SET` with the native name | Ignored; no per-column character sets |
| `collation` | Emits `COLLATE` with the native name | Emits `COLLATE`; the name must exist on the connection |

ASCII is a storage choice, not a portable input validator: SQLite still accepts non-ASCII text.
ASCII characters already use one byte each in UTF-8; declaring ASCII does not imply a universal
fourfold space saving. Binary UUID storage is a separate representation.

For explicit comparison behavior, choose a collation supported by your database:

```php
// MySQL / MariaDB:
$table->addColumn('reference', 'VARCHAR(64)', charset: 'ascii', collation: 'ascii_bin');

// SQLite:
$table->addColumn('reference', 'VARCHAR(64)', collation: 'BINARY');
```

These are backend-specific alternatives, not equivalent portable presets. MySQL's `ascii_bin`
and SQLite's `BINARY` differ on trailing spaces; SQLite's `NOCASE` only folds ASCII case and is
not equivalent to a Unicode case-/accent-insensitive MySQL collation. Unknown or incompatible
native names are rejected by the database, without an automatic fallback.

#### Defaults and modifications

- On MySQL/MariaDB, when neither option is supplied, text columns inherit the table defaults.
  A charset without a collation uses that charset's default collation; a collation without a
  charset selects its associated charset. When both are supplied, they must be compatible.
- SQLite continues to ignore table-level charset/collation options. Column comparisons use
  `BINARY` unless an explicit column collation is supplied; there is no table-default inheritance.
- `modifyColumn()` describes the complete new column definition. Repeat options you want to retain.
  Omitting both returns to the table defaults on MySQL/MariaDB and the implicit `BINARY` collation
  on SQLite; it does not preserve the modified column's previous options automatically.
- The same options work on generated text columns. Renames and unrelated table rebuilds preserve
  existing column options. SQLite introspection reports explicitly declared column collations;
  it does not infer view-result collations or expression collations as column declarations.
- SQLite column modification requires a table rebuild with a current schema. Pass the schema to
  `getStatements($compiler, $schema)` or use the migration runner with a supplied schema for
  transaction and foreign-key management. Re-read metadata after structural changes.

### CHECK constraints

```php
use Hector\Connection\Driver\DriverInfo;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Plan;

$plan = new Plan();
$plan->create('products')
    ->addColumn('price', 'INTEGER')
    ->addCheck('chk_price', 'price >= 0');

// Offline compilation requires the intended MySQL/MariaDB capabilities.
$compiler = new MySQLCompiler((new DriverInfo('mysql', '8.0.16'))->getCapabilities());
$statements = [...$plan->getStatements($compiler)];

$alter = new Plan();
$alter->alter('products')->dropCheck('chk_price')->addCheck('chk_price', 'price >= 1');
```

`addCheck(string $name, Raw|string $expression)` is available on CREATE and ALTER;
`dropCheck(string $name)` is available on ALTER. Names and expressions must be non-empty.
Expressions are emitted verbatim; identifiers are quoted for the dialect.

Enforcement requires MySQL **8.0.16+**, MariaDB **10.2.1+**, or SQLite. Older servers,
Vitess and unknown capabilities are rejected. `AutoCompiler` resolves capabilities from a connection.
`SqliteCompiler` supports offline CREATE; ALTER requires a current `Schema` with complete CHECK metadata.

Introspection exposes `Table::hasCheckMetadata()`, `getChecks()`, `hasCheck()` and `getCheck()`.
`CheckConstraint` retains nullable names, SQL expressions and nullable enforcement state.
Unknown metadata is distinct from a known empty list and prevents SQLite rebuilds.

SQLite preserves CHECKs during rebuilds, including anonymous constraints. CHECK-only changes retain native
table definitions, indexes and triggers. Ambiguous names and unsafe combined transformations raise `PlanException`.
Use the [migration runner](https://gethectororm.com/docs/current/components/migration) for transaction and foreign-key-setting management.

For usage and examples, visit
the [official documentation on **gethectororm.com**](https://gethectororm.com/docs/current/components/schema).
