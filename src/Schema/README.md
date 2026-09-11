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

### Clearing a table in a migration plan

`Plan::purge(string|Table $table, bool $resetIncrement = false): static` removes all rows while preserving
the table structure. Declare it before the schema change that requires an empty table:

```php
use Hector\Schema\Plan\Plan;

public function up(Plan $plan): void
{
    $plan->purge('articles', resetIncrement: true);

    $plan->alter('articles')
        ->modifyColumn('reference', 'VARCHAR(255)', nullable: false);
}
```

| Database | `resetIncrement: false` (default) | `resetIncrement: true` |
| --- | --- | --- |
| MySQL / MariaDB | `DELETE FROM table_name` | `TRUNCATE TABLE table_name` |
| SQLite | `DELETE FROM table_name` | `DELETE FROM table_name`, then `DELETE FROM sqlite_sequence WHERE name = 'table_name'` |

No schema metadata is required to compile a purge. It runs in declaration order among structure operations,
after the compiler's global pre-operations and before its post-operations. The migration runner logs its SQL
and includes it in dry runs without executing it or recording the migration as applied.

#### SQLite counter reset

An explicit reset requires `sqlite_sequence` to exist. SQLite creates this system table when an `AUTOINCREMENT`
table is created. If it exists but has no entry for the target table, the sequence deletion does nothing.
If it does not exist, the reset fails with a normal SQL error. Within the migration runner's SQLite transaction,
that failure also rolls back the preceding deletion; executing the SQL manually outside a transaction does not
provide that guarantee.

For `INTEGER PRIMARY KEY` without `AUTOINCREMENT`, there is no persistent counter to reset and identifiers may
be reused after a purge even with `resetIncrement: false`. That option means no **explicit** reset, not guaranteed
identifier continuity. Reset values follow the database's native counter rules.

#### Constraints, triggers, and failure handling

Purges are explicit: Hector does not infer them from schema changes, inspect incoming foreign keys, disable
foreign-key checks, add `CASCADE`, or fall back from `TRUNCATE` to `DELETE` on failure. Native errors stop the
migration. On MySQL/InnoDB, a foreign key from another table can prevent truncation even if that table is empty.
`DELETE` follows the database's `ON DELETE` actions and fires deletion triggers; MySQL/MariaDB `TRUNCATE` does not
fire those triggers. Changing `resetIncrement` therefore also changes these native effects on MySQL/MariaDB.

MySQL/MariaDB `TRUNCATE` implicitly commits and cannot be rolled back. A later failure can leave a table purged
while its migration remains pending; retrying runs the purge again. `DELETE` followed by `ALTER TABLE` is not
globally rollback-safe either, because the alteration implicitly commits preceding changes before execution.
An enclosing transaction cannot prevent that commit. A `down()` method does not automatically restore deleted data.

Custom dialects implementing `DialectInterface` must implement
`compilePurgeTable(PurgeTable $purgeTable): iterable`, returning the SQL statements for their database.

### Further documentation

For usage and examples, visit
the [official documentation on **gethectororm.com**](https://gethectororm.com/docs/current/components/schema).
