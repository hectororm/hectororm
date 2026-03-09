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
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Table;

class TablePlan extends ObjectPlan
{
    /**
     * Add a column.
     *
     * @param string $name
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
    public function addColumn(
        string $name,
        string $type,
        bool $nullable = false,
        mixed $default = null,
        bool $hasDefault = false,
        bool $autoIncrement = false,
        ?string $after = null,
        bool $first = false,
    ): static {
        $this->addOperation(new AddColumn(
            table: $this->getName(),
            name: $name,
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
     * Drop a column.
     *
     * @param string|Column $column
     *
     * @return static
     */
    public function dropColumn(string|Column $column): static
    {
        $this->addOperation(new DropColumn(
            table: $this->getName(),
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
        $this->addOperation(new ModifyColumn(
            table: $this->getName(),
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
        $this->addOperation(new RenameColumn(
            table: $this->getName(),
            name: $column instanceof Column ? $column->getName() : $column,
            newName: $newName,
        ));

        return $this;
    }

    /**
     * Add an index.
     *
     * @param string $name
     * @param string[] $columns
     * @param string $type
     *
     * @return static
     */
    public function addIndex(string $name, array $columns, string $type = Index::INDEX): static
    {
        $this->addOperation(new AddIndex(
            table: $this->getName(),
            name: $name,
            columns: $columns,
            type: $type,
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
        $this->addOperation(new DropIndex(
            table: $this->getName(),
            name: $index instanceof Index ? $index->getName() : $index,
        ));

        return $this;
    }

    /**
     * Add a foreign key.
     *
     * @param string $name
     * @param string[] $columns
     * @param string|Table $referencedTable
     * @param string[] $referencedColumns
     * @param string $onUpdate
     * @param string $onDelete
     *
     * @return static
     */
    public function addForeignKey(
        string $name,
        array $columns,
        string|Table $referencedTable,
        array $referencedColumns,
        string $onUpdate = ForeignKey::RULE_NO_ACTION,
        string $onDelete = ForeignKey::RULE_NO_ACTION,
    ): static {
        $this->addOperation(new AddForeignKey(
            table: $this->getName(),
            name: $name,
            columns: $columns,
            referencedTable: $referencedTable instanceof Table ? $referencedTable->getName() : $referencedTable,
            referencedColumns: $referencedColumns,
            onUpdate: $onUpdate,
            onDelete: $onDelete,
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
        $this->addOperation(new DropForeignKey(
            table: $this->getName(),
            name: $foreignKey instanceof ForeignKey ? $foreignKey->getName() : $foreignKey,
        ));

        return $this;
    }
}
