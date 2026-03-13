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

use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Table;

abstract class TableOperation extends OperationGroup
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
        $this->add(new AddColumn(
            table: $this->getObjectName(),
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
        $this->add(new AddIndex(
            table: $this->getObjectName(),
            name: $name,
            columns: $columns,
            type: $type,
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
        $this->add(new AddForeignKey(
            table: $this->getObjectName(),
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
     * Create a trigger on this table.
     *
     * @param string $name
     * @param string $timing BEFORE, AFTER or INSTEAD OF
     * @param string $event INSERT, UPDATE or DELETE
     * @param string $body SQL body of the trigger
     * @param string|null $when Optional WHEN condition (SQLite only)
     *
     * @return static
     */
    public function createTrigger(
        string $name,
        string $timing,
        string $event,
        string $body,
        ?string $when = null,
    ): static {
        $this->add(new CreateTrigger(
            table: $this->getObjectName(),
            name: $name,
            timing: $timing,
            event: $event,
            body: $body,
            when: $when,
        ));

        return $this;
    }
}
