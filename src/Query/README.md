# Hector Query

[![Latest Version](https://img.shields.io/packagist/v/hectororm/query.svg?style=flat-square)](https://github.com/hectororm/query/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/query/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/query.svg?style=flat-square)](https://github.com/hectororm/query/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Query** is the query module of Hector ORM. Can be used independently of ORM.

## Installation

You can install **Hector Query** with [Composer](https://getcomposer.org/), it's the recommended installation.

```shell
$ composer require hectororm/query
```

## Query Builder

### Usage

You can initialize the query builder with a `Connection` object.

```php
use Hector\Connection\Connection;
use Hector\Query\QueryBuilder;

$connection = new Connection('...');
$queryBuilder = new QueryBuilder($connection);

$result = $queryBuilder
    ->select('table')
    ->where('field1', 'foo')
    ->where('field2', '>=', 2)
    ->fetchAll();
```

### Select / Insert / Update / Delete / Union

You can do a select/insert/update/delete request with specific objects:

- Select : `Hector\Query\Select` class
- Insert : `Hector\Query\Insert` class
- Update : `Hector\Query\Update` class
- Delete : `Hector\Query\Delete` class
- Union : `Hector\Query\Union` class

```php
use Hector\Query\Select;
use Hector\Query\Insert;
use Hector\Query\Update;
use Hector\Query\Delete;
use Hector\Query\Union;

$select = new Select();
$insert = new Insert();
$update = new Update();
$delete = new Delete();
$union = new Union();
```

All this classes implements `StatementInterface` interface. This interface provides one method to get statement and bindings:

`StatementInterface::getStatement(BindParamList $bindParams)`

Example of use:

```php
use Hector\Connection\Connection;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Select;

$connection = new Connection('...');
$select = new Select();
$select
    ->from('table')
    ->where('field', 'value');

$binds = new BindParamList();
$statement = $select->getStatement($binds);

$result = $connection->fetchAll($statement, $binds);
```

### Conditions

`Hector Query` has support of having and where conditions. Methods are sames, just replace "where" by "having" in method
name.

#### Where / Having

```php
/** @var QueryBuilder $queryBuilder */
use Hector\Query\QueryBuilder;

$queryBuilder
    ->from('table', 'alias')
    ->where('field', '=', 'value')
    ->orWhere('field', '=', 'value2');
```

#### Shortcuts

- `QueryBuilder::whereIn($column, array $values)`
- `QueryBuilder::whereNotIn($column, array $values)`
- `QueryBuilder::whereBetween($column, $value1, $value2)`
- `QueryBuilder::whereNotBetween($column, $value1, $value2)`
- `QueryBuilder::whereGreaterThan($column, $value)`
- `QueryBuilder::whereGreaterThanOrEqual($column, $value)`
- `QueryBuilder::whereLessThan($column, $value)`
- `QueryBuilder::whereLessThanOrEqual($column, $value)`
- `QueryBuilder::whereExists($statement)`
- `QueryBuilder::whereNotExists($statement)`
- `QueryBuilder::whereContains($string)`
- `QueryBuilder::whereStartsWith($string)`
- `QueryBuilder::whereEndsWith($string)`

### Columns

You can specify columns name and alias with method:

`QueryBuilder::column($column, $alias)`

Repeat call of this method, add a new column to the result rows ; you can reset columns with method `QueryBuilder::resetColumns()`.

Or pass an array of column names:

`QueryBuilder::columns(array $columnNames)`

### Group

You can group results with method:

`QueryBuilder::groupBy($column)`

Repeat call of this method, add a new group ; you can reset groups with method `QueryBuilder::resetGroups()`.

If you want set `WITH ROLLUP` modifier to your statement, you can do it with method:

`QueryBuilder::groupByWithRollup(bool $withRollup = true)`

### Order

You can order results with method:

`QueryBuilder::orderBy($column, $order)`

Repeat call of this method, add a new order ; you can reset orders with method `QueryBuilder::resetOrder()`.

A shortcut is available if you want to do a random order:

`QueryBuilder::random()`

### Limit

You can limit results with methods:

- `QueryBuilder::limit(int $limit, int $offset = null)`
- `QueryBuilder::offset(int $offset)`

If you want reset limits, uses method `QueryBuilder::resetLimit()`.

### Assignments

For Insert/Update statements, you need to assign values with method :

`QueryBuilder::assign($column, $value)`

Repeat call of this method, add a new assignment to the statement ; you can reset assignments with method `QueryBuilder::resetAssignments()`.

Or pass an associative array with column names and values:

`QueryBuilder::assigns(array|StatementInterface $columnValues)`

### Jointures

Three methods are available to do jointures:

- `QueryBuilder::innerJoin($table, $condition, ?string $alias = null)`
- `QueryBuilder::leftJoin($table, $condition, ?string $alias = null)`
- `QueryBuilder::rightJoin($table, $condition, ?string $alias = null)`

If you want reset jointures, uses method `QueryBuilder::resetJoin()`.

### Union

An `Union` class is available to make unions with select.

```php
use Hector\Connection\Connection;
use Hector\Query\Select;
use Hector\Query\Union;

$connection = new Connection('...');
$union = new Union();

/** @var Select $select1 */
/** @var Select $select2 */
$union->addSelect($select1, $select2);
```

`Union` class is a `StatementInterface`, so refers to the related paragraph to use it.

### Fetch results

3 methods to fetch result:

- `QueryBuilder::fetchOne(): ?array`
  Get first row of statement results.
- `QueryBuilder::fetchAll(): Generator`
  Get all rows of statement results, uses `Generator` class.
- `QueryBuilder::fetchColumn(int $column = 0): Generator`
  Get specified column value of all rows of statement results, uses `Generator` class.
  
To known how use Generator, refers to the PHP documentation: https://www.php.net/manual/class.generator.php

### Count results

A shortcut method is available in `QueryBuilder` class to count results.

```php
/** @var QueryBuilder $queryBuilder */
use Hector\Query\QueryBuilder;

$queryBuilder
    ->from('table', 'alias')
    ->where('field', '=', 'value')
    ->orWhere('field', '=', 'value2');

$count = $queryBuilder->count();
$results = $queryBuilder->fetchAll();
```

This method reset columns, limit and order of query ; but don't modify the query builder, so you can continue to use it to get results for example.

### Distinct

```php
/** @var QueryBuilder $queryBuilder */
use Hector\Query\QueryBuilder;

$queryBuilder
    ->from('table', 'alias')
    ->where('field', '=', 'value')
    ->orWhere('field', '=', 'value2')
    ->distinct();

$count = $queryBuilder->count();
$results = $queryBuilder->fetchAll();

### Exists

A shortcut method is available in `QueryBuilder` class to do an exists query.

```php
/** @var QueryBuilder $queryBuilder */
use Hector\Query\QueryBuilder;

$queryBuilder
    ->from('table', 'alias')
    ->where('field', '=', 'value')
    ->orWhere('field', '=', 'value2');

$exists = $queryBuilder->exists();
```

This method don't modify the query builder, so you can continue to use it to get results for example.

### Insert / Update / Delete

Shortcut methods are available in `QueryBuilder` class to do an insert, an update or a delete.

```php
/** @var QueryBuilder $queryBuilder */
use Hector\Query\QueryBuilder;
use Hector\Query\Select;

$queryBuilder
    ->from('table', 'alias')
    ->where('field', '=', 'value')
    ->orWhere('field', '=', 'value2');

$affectedRows = $queryBuilder->insert(['field' => 'value', 'field2' => 'value2']);
$affectedRows = $queryBuilder->insert((new Select())->from('table_src'));
$affectedRows = $queryBuilder->update(['field' => 'value']);
$affectedRows = $queryBuilder->delete();
```

These methods don't modify the query builder, so you can continue to use it to get results for example.

To ignore duplicates :

```php
$queryBuilder->delete();
```