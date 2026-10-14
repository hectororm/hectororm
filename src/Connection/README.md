# Hector Connection

[![Latest Version](https://img.shields.io/packagist/v/hectororm/connection.svg?style=flat-square)](https://github.com/hectororm/connection/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/connection/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/connection.svg?style=flat-square)](https://github.com/hectororm/connection/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Connection** is the PDO connection module of Hector ORM. Can be used independently of ORM.

📖 **[Full documentation](https://gethectororm.com/docs/current/components/connection)**

## Installation

You can install **Hector Connection** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require hectororm/connection
```

## Documentation

### CHECK constraint capabilities

`DriverCapabilities::hasCheckConstraints()` reports enforced CHECK support.
`DriverCapabilities::hasDropCheck()` reports native `ALTER TABLE ... DROP CHECK`
syntax support.

| Engine | `hasCheckConstraints()` | `hasDropCheck()` |
| --- | --- | --- |
| MySQL 8.0.16+ | `true` | `true` (`DROP CHECK`) |
| MariaDB 10.2.1+ | `true` | `false` (`DROP CONSTRAINT` in the MySQL dialect) |
| SQLite | `true` | `false` (table rebuild required) |
| PostgreSQL | `true` | `false` (`DROP CONSTRAINT`) |
| Older MySQL/MariaDB | `false` | `false` |
| Vitess / unknown | `false` | `false` |

**Compatibility:** third-party implementations of `DriverCapabilities` must add
both methods when upgrading. Return `false` when support cannot be established.
These values describe engine support; they do not inspect session settings that
disable enforcement or imply availability of a schema plan dialect. PostgreSQL
has native CHECK support but currently has no schema plan compiler.

For offline use, resolve capabilities from the intended engine and version:

```php
use Hector\Connection\Driver\DriverInfo;

$capabilities = (new DriverInfo('mysql', '8.0.16'))->getCapabilities();
$supportsChecks = $capabilities->hasCheckConstraints();
```

Consumers must treat missing capabilities or a `false` result as unsupported for
enforced CHECK operations, rather than assuming a recent MySQL-compatible server.

For usage and examples, visit
the [official documentation on **gethectororm.com**](https://gethectororm.com/docs/current/components/connection).
