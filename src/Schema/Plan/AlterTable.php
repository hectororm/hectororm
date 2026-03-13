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

namespace Hector\Schema\Plan;

use Hector\Schema\Column;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyCharset;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;

final class AlterTable extends TableOperation
{
    /**
     * Drop a column.
     *
     * @param string|Column $column
     *
     * @return static
     */
    public function dropColumn(string|Column $column): static
    {
        $this->add(new DropColumn(
            table: $this->getObjectName(),
            name: $column instanceof Column ? $column->getName() : $column,
        ));

        return $this;
    }

    /**
     * Modify a column.
     *
     * @param string|Column $column
     * @param string $type
     * @param bool $nullable
     * @param mixed $default
     * @param bool $hasDefault
     * @param bool $autoIncrement
     * @param string|null $after
     * @param bool $first
     *
     * @return static
     */
    public function modifyColumn(
        string|Column $column,
        string $type,
        bool $nullable = false,
        mixed $default = null,
        bool $hasDefault = false,
        bool $autoIncrement = false,
        ?string $after = null,
        bool $first = false,
    ): static {
        $this->add(new ModifyColumn(
            table: $this->getObjectName(),
            name: $column instanceof Column ? $column->getName() : $column,
            type: $type,
            nullable: $nullable,
            default: $default,
            hasDefault: $hasDefault,
            autoIncrement: $autoIncrement,
            after: $after,
            first: $first,
        ));

        return $this;
    }

    /**
     * Rename a column.
     *
     * @param string|Column $column
     * @param string $newName
     *
     * @return static
     */
    public function renameColumn(string|Column $column, string $newName): static
    {
        $this->add(new RenameColumn(
            table: $this->getObjectName(),
            name: $column instanceof Column ? $column->getName() : $column,
            newName: $newName,
        ));

        return $this;
    }

    /**
     * Drop an index.
     *
     * @param string|Index $index
     *
     * @return static
     */
    public function dropIndex(string|Index $index): static
    {
        $this->add(new DropIndex(
            table: $this->getObjectName(),
            name: $index instanceof Index ? $index->getName() : $index,
        ));

        return $this;
    }

    /**
     * Drop a foreign key.
     *
     * @param string|ForeignKey $foreignKey
     *
     * @return static
     */
    public function dropForeignKey(string|ForeignKey $foreignKey): static
    {
        $this->add(new DropForeignKey(
            table: $this->getObjectName(),
            name: $foreignKey instanceof ForeignKey ? $foreignKey->getName() : $foreignKey,
        ));

        return $this;
    }

    /**
     * Modify charset and collation.
     *
     * @param string $charset
     * @param string|null $collation
     *
     * @return static
     */
    public function modifyCharset(string $charset, ?string $collation = null): static
    {
        $this->add(new ModifyCharset(
            table: $this->getObjectName(),
            charset: $charset,
            collation: $collation,
        ));

        return $this;
    }

    /**
     * Rename the table.
     *
     * @param string $newName
     *
     * @return static
     */
    public function renameTable(string $newName): static
    {
        $this->add(new RenameTable(
            table: $this->getObjectName(),
            newName: $newName,
        ));

        return $this;
    }

    /**
     * Drop a trigger on this table.
     *
     * @param string $name
     *
     * @return static
     */
    public function dropTrigger(string $name): static
    {
        $this->add(new DropTrigger(
            table: $this->getObjectName(),
            name: $name,
        ));

        return $this;
    }
}
