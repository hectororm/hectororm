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

## Installation

You can install **Hector Schema** with [Composer](https://getcomposer.org/), it's the recommended installation.

```bash
$ composer require hectororm/schema
```

## DBMS compatibility

| DBMS    | Version | Compatibility |
|---------|:-------:|:-------------:|
| MySQL   |   8.4   |       ✔       |
| MySQL   |   8.0   |       ✔       |
| MySQL   |   5.7   |       ✔       |
| MySQL   |   5.6   |       ✔       |
| MariaDB |  11.7   |       ✔       |
| MariaDB |  11.4   |       ✔       |
| MariaDB |  10.11  |       ✔       |
| MariaDB |  10.6   |       ✔       |
| MariaDB |  10.5   |       ✔       |
| Sqlite  |   3.x   |       ✔       |

If you have any information on the compatibility of other versions, feel free to make a PR.

## Usage

### Generate a schema

```php
use Hector\Connection\Connection;
use Hector\Schema\Generator\MySQL;

$connection = new Connection('...');
$generator = new MySQL($connection);

$schema = $generator->generateSchema('schema_name'); // Returns a `Hector\Schema\Schema` object
$container = $generator->generateSchemas('schema1_name', 'schema2_name'); // Returns a `Hector\Schema\SchemaContainer` object
```

Generators:

- `Hector\Schema\Generato\MySQL` for MySQL or derived DBMS
- `Hector\Schema\Generato\Sqlite` for Sqlite

### Cache

This library don't provide cache management for schemas.
It's to the user library to imagine how store the generated schema.

To help to do that, library only provide serialization of objects and restoration of inheritance between objects.

### Schema

Description of classes represents the a schema.

#### `Hector\Schema\SchemaContainer`

It's a container of schema. Methods available:

- `SchemaContainer::getSchemas(?string $connection = null): Generator` Returns a generator of `Hector\Schema\Schema` objects, you can pass a specific connection
- `SchemaContainer::hasSchema(string $name, ?string $connection = null): bool` Check if container has a schema
- `SchemaContainer::getSchema(string $name, ?string $connection = null): Schema` Returns representation of a schema, an `Hector\Schema\Schema` object
- `SchemaContainer::hasTable(string $name, ?string $schemaName = null, ?string $connection = null): bool` Check if a schema in the container has a table
- `SchemaContainer::getTable(string $name, ?string $schemaName = null, ?string $connection = null): Table` Returns representation of a table, an `Hector\Schema\Table` object

It's an iterable class, returns `Hector\Schema\Schema` objects.

#### `Hector\Schema\Schema`

Represent a schema. Methods available:

- `Schema::getName(bool $quoted = false): string` Returns the name of schema
- `Schema::getCharset(): string` Returns the charset of schema
- `Schema::getCollation(): string` Returns the charset of schema
- `Schema::getTables(): Generator` Returns a generator of `Hector\Schema\Table` objects
- `Schema::hasTable(string $name): bool` Check if schema has a table
- `Schema::getTable(string $name): Table` Returns representation of a table, an `Hector\Schema\Table` object
- `Schema::getContainer(): ?SchemaContainer` Returns the container of schema if it's a part of a container

It's an iterable class, returns `Hector\Schema\Table` objects.

#### `Hector\Schema\Table`

Represent a table of a schema. Methods available:

- `Table::getSchemaName(bool $quoted = false): string`
- `Table::getName(bool $quoted = false): string`
- `Table::getFullName(bool $quoted = false): string`
- `Table::getType(): string`
- `Table::getCharset(): ?string`
- `Table::getCollation(): ?string`
- `Table::getColumns(): Generator`
- `Table::getColumnsName(bool $quoted = false, ?string $tableAlias = null): array`
- `Table::hasColumn(string $name): bool`
- `Table::getColumn(string $name): Column`
- `Table::getAutoIncrementColumn(): ?Column`
- `Table::getIndexes(?string $type = null): Generator`
- `Table::hasIndex(string $name): bool`
- `Table::getIndex(string $name): Index`
- `Table::getPrimaryIndex(): ?Index`
- `Table::getForeignKeys(Table $table = null): Generator`
- `Table::getSchema(): Schema`

#### `Hector\Schema\Column`

Represent a column of a table. Methods available:

- `Column::getName(bool $quoted = false, ?string $tableAlias = null): string`
- `Column::getFullName(bool $quoted = false): string`
- `Column::getPosition(): int`
- `Column::getDefault(): mixed`
- `Column::isNullable(): bool`
- `Column::getType(): string`
- `Column::isAutoIncrement(): bool`
- `Column::getMaxlength(): ?int`
- `Column::getNumericPrecision(): ?int`
- `Column::getNumericScale(): ?int`
- `Column::isUnsigned(): bool`
- `Column::getCharset(): ?string`
- `Column::getCollation(): ?string`
- `Column::getTable(): Table`
- `Column::isPrimary(): bool`

#### `Hector\Schema\Index`

Represent an index of a table. Methods available:

- `Index::getName(): string`
- `Index::getType(): string`
- `Index::getColumnsName(bool $quoted = false, ?string $tableAlias = null): array`
- `Index::getTable(): Table`
- `Index::getColumns(): array`
- `Index::hasColumn(Column $column): bool`

#### `Hector\Schema\ForeignKey`

Represent a foreign key of a table. Methods available:

- `ForeignKey::getName(): string`
- `ForeignKey::getColumnsName(bool $quoted = false, ?string $tableAlias = null): array`
- `ForeignKey::getReferencedSchemaName(): string`
- `ForeignKey::getReferencedTableName(): string`
- `ForeignKey::getReferencedColumnsName(bool $quoted = false, ?string $tableAlias = null): array`
- `ForeignKey::getUpdateRule(): string`
- `ForeignKey::getDeleteRule(): string`
- `ForeignKey::getTable(): Table`
- `ForeignKey::getColumns(): Generator`
- `ForeignKey::getReferencedTable(): ?Table`
- `ForeignKey::getReferencedColumns(): Generator`
