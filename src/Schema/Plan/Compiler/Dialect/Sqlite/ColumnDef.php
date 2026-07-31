<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema\Plan\Compiler\Dialect\Sqlite;

use Hector\Schema\Column;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Raw;

/**
 * A column entry of the SQLite rebuild diff model.
 */
final class ColumnDef
{
    /**
     * @param string                $name
     * @param string                $type
     * @param bool                  $nullable
     * @param Raw|string|int|float|bool|null $default
     * @param bool                  $hasDefault
     * @param bool                  $autoIncrement
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public Raw|string|int|float|bool|null $default,
        public bool $hasDefault,
        public bool $autoIncrement,
    ) {
    }

    /**
     * Build from an introspected schema column.
     *
     * @param Column $column
     * @param string $type Reconstructed SQL type (with length/precision).
     *
     * @return self
     */
    public static function fromSchema(Column $column, string $type): self
    {
        return new self(
            name: $column->getName(),
            type: $type,
            nullable: $column->isNullable(),
            default: $column->getDefault(),
            hasDefault: null !== $column->getDefault(),
            autoIncrement: $column->isAutoIncrement(),
        );
    }

    /**
     * Build from an AddColumn / ModifyColumn operation.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return self
     */
    public static function fromOperation(AddColumn|ModifyColumn $operation): self
    {
        return new self(
            name: $operation->getName(),
            type: $operation->getType(),
            nullable: $operation->isNullable(),
            default: $operation->getDefault(),
            hasDefault: $operation->hasDefault(),
            autoIncrement: $operation->isAutoIncrement(),
        );
    }

    /**
     * Return a copy renamed to the given name.
     *
     * @param string $newName
     *
     * @return self
     */
    public function withName(string $newName): self
    {
        $clone = clone $this;
        $clone->name = $newName;

        return $clone;
    }
}
