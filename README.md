# Hector ORM

[![Latest Version](https://img.shields.io/packagist/v/hectororm/hectororm.svg?style=flat-square)](https://github.com/hectororm/hectororm/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/hectororm/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/hectororm.svg?style=flat-square)](https://github.com/hectororm/hectororm/blob/main/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/hectororm/hectororm/tests.yml?branch=main&style=flat-square&label=tests)](https://github.com/hectororm/hectororm/actions/workflows/tests.yml?query=branch%3Amain)
[![Quality Grade](https://img.shields.io/codacy/grade/f60b4671c33e401492a7bba77df99c8a/main.svg?style=flat-square)](https://app.codacy.com/gh/hectororm/hectororm)
[![Total Downloads](https://img.shields.io/packagist/dt/hectororm/hectororm.svg?style=flat-square)](https://packagist.org/packages/hectororm/hectororm)

**Hector ORM** is a modern, modular, and lightweight Object-Relational Mapping (ORM) framework for PHP.

It features a fully decoupled architecture, offering individual components for collections, connections, data types,
schema introspection, and query building.  
You can use each package independently or as part of the complete Hector ORM solution.

> **All development, issues, and pull requests are managed in this monorepo.**  
> Sub-repositories are automatically synchronized for each package.

---

## 🚀 Features

- Decoupled, modular design
- Advanced query builder
- Flexible schema introspection
- Type-safe data mapping
- Robust collection utilities
- Lightweight, modern PHP codebase

## 📦 Installation

You can install Hector ORM and its components using [Composer](https://getcomposer.org):

### Full ORM package

```bash
composer require hectororm/hectororm
```

### Individual components (example)

```bash
composer require hectororm/orm
composer require hectororm/collection
composer require hectororm/connection
composer require hectororm/data-types
composer require hectororm/query
composer require hectororm/schema
```

## 📚 Documentation

Full documentation, usage guides, and API reference are available at
👉 [https://gethectororm.com](https://gethectororm.com)

## Contributing

Please open all issues and pull requests in this repository.
