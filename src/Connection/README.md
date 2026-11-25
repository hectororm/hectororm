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

### Date/time bind values and storage timezone

`DateTimeInterface` values can be bound to any statement. When the statement is
executed, the connection converts them to the storage timezone of the database,
then formats them:

```php
use Hector\Connection\Connection;

$connection = new Connection(
    'mysql:host=localhost;dbname=app',
    dateTimeFormat: 'Y-m-d H:i:s', // default
    timezone: new DateTimeZone('UTC'), // storage timezone of the database
);

$connection->execute(
    'INSERT INTO event (starts_at) VALUES (?)',
    [new DateTimeImmutable('2026-01-02 03:04:05+09:00')]
); // binds "2026-01-01 18:04:05"
```

| Parameter | Default | Meaning |
| --- | --- | --- |
| `dateTimeFormat` | `Y-m-d H:i:s` | Format of the bound value, e.g. `Y-m-d H:i:s.u` for `DATETIME(6)` columns |
| `timezone` | `null` | Storage timezone of the database, also applied to the [session of the server](#session-timezone); `null` means the PHP default timezone, and leaves the session untouched |

Both parameters are also available on `Connection::fromPdo()`, and through the
`datetime_format` and `timezone` options of `OrmFactory::connection()`.

Use the same timezone as the `DateTimeType` of your entities (which also falls back
to the PHP default timezone), so that raw statements, the query builder and the
entity mapping read and write dates in the same timezone.

Limits:

- There is a single format per connection, whatever the column: format the value
  yourself for `DATE` or `TIME` columns when the engine compares them as text
  (SQLite). Strings are bound as is.
- The default format drops microseconds.

### Session timezone

A date/time value without timezone is read by the server in the timezone of its
session. When the connection has a `timezone`, the session is set to it as soon as
the connection is opened, so that the values computed by the server (`NOW()`,
`CURRENT_TIMESTAMP`, `DEFAULT CURRENT_TIMESTAMP` columns, MySQL/MariaDB `TIMESTAMP`
columns) and the values bound from PHP are in the same timezone:

```php
$connection = new Connection('mysql:host=localhost;dbname=app', timezone: new DateTimeZone('UTC'));

$connection->execute(
    'INSERT INTO event (created_at, starts_at) VALUES (NOW(), ?)',
    [new DateTimeImmutable()]
); // both values are in UTC
```

| Engine | Statement |
| --- | --- |
| MySQL, MariaDB | `SET time_zone = ...` |
| PostgreSQL | `SET TIME ZONE ...` |
| SQLite, other drivers | None: there is no session timezone to set |

It applies to the read/write and read-only connections, and to the PDO objects
given to `Connection::fromPdo()`. Without a `timezone`, nothing is sent and the
session keeps the timezone of the server.

- `UTC` is sent as the `+00:00` offset on MySQL/MariaDB, which works on any server.
- A named timezone (`Europe/Paris`) follows daylight saving time, but needs the
  [timezone tables](https://dev.mysql.com/doc/refman/en/time-zone-support.html) to
  be loaded on MySQL/MariaDB.
- A fixed offset (`+02:00`) works on any server, but does not follow daylight saving
  time.
- A timezone refused by the server throws a `ConnectionException` when the
  connection is opened.

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
