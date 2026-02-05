# Hector Pagination

[![Latest Version](https://img.shields.io/packagist/v/hectororm/pagination.svg?style=flat-square)](https://github.com/hectororm/pagination/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/pagination/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/pagination.svg?style=flat-square)](https://github.com/hectororm/pagination/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

**Hector Pagination** provides a set of interfaces and implementations for handling pagination in PHP applications.
It supports multiple pagination strategies: offset-based, cursor-based (keyset), and range-based pagination.

📖 **[Full documentation](https://gethectororm.com/docs/current/components/pagination)**

## Installation

```shell
composer require hectororm/pagination
```

## Pagination Types

| Type   | Class              | Use Case                               |
|--------|--------------------|----------------------------------------|
| Offset | `OffsetPagination` | Simple page-based navigation           |
| Cursor | `CursorPagination` | Keyset pagination for large datasets   |
| Range  | `RangePagination`  | RFC 7233 style (Content-Range headers) |

## Quick Start

```php
use Hector\Pagination\OffsetPagination;
use Hector\Pagination\Paginator\OffsetPaginator;

// 1. Create paginator (injectable as service)
$paginator = new OffsetPaginator(
    pageParam: 'page',
    perPageParam: 'per_page',
    defaultPerPage: 15,
    maxPerPage: 100,
);

// 2. Parse request: ?page=3&per_page=20
$request = $paginator->createRequest($serverRequest);

// 3. Query your data
$results = $repository->findAll($request->getLimit(), $request->getOffset());

// 4. Build pagination
$pagination = new OffsetPagination(
    items: $results,
    perPage: $request->perPage,
    currentPage: $request->page,
    hasMore: count($results) >= $request->perPage,
);

// 5. Prepare response with Link headers
$response = $paginator->prepareResponse($response, $serverRequest->getUri(), $pagination);
```

## Features

- **Paginators**: High-level API for request parsing and response preparation
- **Navigators**: Generate navigation links (first, prev, next, last)
- **URI Builders**: Customizable query parameter generation
- **Cursor Encoders**: Base64 and HMAC-signed cursors
- **Cursor Storage**: Server-side cursor state (PSR-16 cache)
- **JSON Serialization**: All pagination classes implement `JsonSerializable`
- **PSR-7 Compatible**: Works with any PSR-7 implementation

📖 **[See full documentation for all features](https://gethectororm.com/components/pagination)**
