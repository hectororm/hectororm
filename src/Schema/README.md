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
