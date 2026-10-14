# Hector Migration

[![Latest Version](https://img.shields.io/packagist/v/hectororm/migration.svg?style=flat-square)](https://github.com/hectororm/migration/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/migration/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/migration.svg?style=flat-square)](https://github.com/hectororm/migration/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Migration** orchestrates database migrations for Hector ORM. It provides migration providers, trackers, and a
transaction-safe runner built on top of the [Plan system](https://gethectororm.com/docs/current/components/plan). Can be
used independently of ORM.

## Installation

You can install **Hector Migration** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require hectororm/migration
```

## Documentation

### CHECK migrations

```php
use Hector\Migration\ReversibleMigrationInterface;
use Hector\Schema\Plan\Plan;

class RequireNonNegativePrice implements ReversibleMigrationInterface
{
    public function up(Plan $plan): void
    {
        $plan->alter('products')->addCheck('chk_price', 'price >= 0');
    }

    public function down(Plan $plan): void
    {
        $plan->alter('products')->dropCheck('chk_price');
    }
}
```

Use `AutoCompiler`, or explicit capabilities for offline MySQL/MariaDB compilation.
For SQLite ALTER operations, pass a current schema from `Hector\Schema\Generator\Sqlite` to the runner.
The runner refreshes supplied SQLite metadata between executed migrations.

The runner compiles the whole plan before executing SQL. SQLite rebuilds run in their own transaction:
foreign-key checks are disabled beforehand, integrity is checked before commit, and the original setting is
restored after success or failure. With `DbTracker` on the same connection, schema/data changes and tracking
are committed or rolled back together. CHECK additions fail when SQLite session enforcement is disabled.
MySQL/MariaDB DDL implicitly commits; a later failure can leave schema changes applied but untracked.

`up(dryRun: true)` and `down(dryRun: true)` compile and log SQL without executing migration statements or
recording their application. Dry-run uses the available schema snapshot; it does not simulate earlier migrations.

For usage and examples, visit
the [official documentation on **gethectororm.com**](https://gethectororm.com/docs/current/components/migration).
