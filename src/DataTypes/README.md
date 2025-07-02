# Hector Data Types

[![Latest Version](https://img.shields.io/packagist/v/hectororm/data-types.svg?style=flat-square)](https://github.com/hectororm/data-types/releases)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/hectororm/data-types/php?version=dev-main&style=flat-square)
[![Software license](https://img.shields.io/github/license/hectororm/data-types.svg?style=flat-square)](https://github.com/hectororm/data-types/blob/main/LICENSE)

> **Note**
>
> This repository is a **read-only split** from the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> For contributions, issues, or more information, please visit
> the [main HectorORM repository](https://github.com/hectororm/hectororm).
>
> **Do not open issues or pull requests here.**

---

Hector Data Types is the module to manage types of Hector ORM. Can be used independently of ORM.

## Installation

You can install **Hector Data Types** with [Composer](https://getcomposer.org/), it's the recommended installation.

```shell
$ composer require hectororm/data-types
```

## Usage

Each type converter implement interface:

```php
use Hector\DataTypes\ExpectedType;

interface TypeInterface
{
    /**
     * From schema function.
     *
     * @return string|null
     */
    public function fromSchemaFunction(): ?string;

    /**
     * From schema to entity.
     *
     * @param mixed $value
     * @param ExpectedType|null $expected
     *
     * @return mixed
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed;

    /**
     * To schema function.
     *
     * @return string|null
     */
    public function toSchemaFunction(): ?string;

    /**
     * From entity to schema.
     *
     * @param mixed $value
     * @param ExpectedType|null $expected
     *
     * @return mixed
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): mixed;

    /**
     * Get binding type.
     * Must return a PDO::PARAM_* value.
     *
     * @return int|null
     */
    public function getBindingType(): ?int;
}
```

Attempted excepted type is a PHP representation of a property type.
