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

namespace Hector\Schema\Plan\Operation;

use Hector\Schema\ForeignKey;

class AddForeignKey implements ForeignKeyOperationInterface, PostOperationInterface
{
    public function __construct(
        private string $table,
        private string $name,
        private array $columns,
        private string $referencedTable,
        private array $referencedColumns,
        private string $onUpdate = ForeignKey::RULE_NO_ACTION,
        private string $onDelete = ForeignKey::RULE_NO_ACTION,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->table;
    }

    /**
     * Get foreign key name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get columns.
     *
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get referenced table name.
     *
     * @return string
     */
    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }

    /**
     * Get referenced columns.
     *
     * @return string[]
     */
    public function getReferencedColumns(): array
    {
        return $this->referencedColumns;
    }

    /**
     * Get on update rule.
     *
     * @return string
     */
    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    /**
     * Get on delete rule.
     *
     * @return string
     */
    public function getOnDelete(): string
    {
        return $this->onDelete;
    }
}
