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

use Hector\Schema\Index;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\OperationInterface;

/**
 * Mutable in-memory model of a table's shape, used by the SQLite rebuild.
 *
 * Starts as the current (introspected) shape, then each requested operation is
 * applied via {@see apply()} to produce the target shape. Column renames are
 * recorded so the data-migration mapping can be derived.
 */
final class TableDiff
{
    /** @var array<string, string> old column name => new column name */
    private array $renames = [];

    /** @var string[] column names as they existed before any operation */
    private array $originalColumnNames;

    /**
     * @param array<string, ColumnDef>     $columns
     * @param array<string, IndexDef>      $indexes
     * @param array<string, ForeignKeyDef> $foreignKeys
     */
    public function __construct(private array $columns, private array $indexes, private array $foreignKeys)
    {
        $this->originalColumnNames = array_keys($this->columns);
    }

    /**
     * Apply a single ALTER operation to the model.
     *
     * @param OperationInterface $operation
     */
    public function apply(OperationInterface $operation): void
    {
        match ($operation::class) {
            AddColumn::class => $this->putColumn(ColumnDef::fromOperation($operation)),
            ModifyColumn::class => $this->modifyColumn($operation),
            DropColumn::class => $this->dropColumn($operation->getName()),
            RenameColumn::class => $this->renameColumn($operation->getName(), $operation->getNewName()),
            AddIndex::class => $this->putIndex(new IndexDef(
                $operation->getName(),
                $operation->getColumns(),
                $operation->getType(),
            )),
            DropIndex::class => $this->dropIndex($operation->getName()),
            AddForeignKey::class => $this->putForeignKey(new ForeignKeyDef(
                $operation->getName(),
                $operation->getColumns(),
                $operation->getReferencedTable(),
                $operation->getReferencedColumns(),
                $operation->getOnUpdate(),
                $operation->getOnDelete(),
            )),
            DropForeignKey::class => $this->dropForeignKey($operation->getName()),
            default => null,
        };
    }

    private function putColumn(ColumnDef $column): void
    {
        $this->columns[$column->name] = $column;
    }

    private function modifyColumn(ModifyColumn $operation): void
    {
        if (false === isset($this->columns[$operation->getName()])) {
            return;
        }

        $this->columns[$operation->getName()] = ColumnDef::fromOperation($operation);
    }

    private function dropColumn(string $name): void
    {
        unset($this->columns[$name]);
    }

    private function renameColumn(string $oldName, string $newName): void
    {
        if (false === isset($this->columns[$oldName])) {
            return;
        }

        $this->columns[$newName] = $this->columns[$oldName]->withName($newName);
        unset($this->columns[$oldName]);
        $this->renames[$oldName] = $newName;
    }

    private function putIndex(IndexDef $index): void
    {
        $this->indexes[$index->name] = $index;
    }

    private function dropIndex(string $name): void
    {
        unset($this->indexes[$name]);
    }

    private function putForeignKey(ForeignKeyDef $foreignKey): void
    {
        $this->foreignKeys[$foreignKey->name] = $foreignKey;
    }

    private function dropForeignKey(string $name): void
    {
        unset($this->foreignKeys[$name]);
    }

    /**
     * @return array<string, ColumnDef>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<string, ForeignKeyDef>
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return IndexDef[]
     */
    public function primaryIndexes(): array
    {
        return array_values(array_filter(
            $this->indexes,
            fn(IndexDef $idx): bool => Index::PRIMARY === $idx->type,
        ));
    }

    /**
     * @return IndexDef[]
     */
    public function nonPrimaryIndexes(): array
    {
        return array_values(array_filter(
            $this->indexes,
            fn(IndexDef $idx): bool => Index::PRIMARY !== $idx->type,
        ));
    }

    /**
     * Build the data-migration mapping: source column => target column.
     *
     * Only columns present in both the original and target shapes are kept
     * (matched by original name, or by their new name when renamed).
     *
     * @return array<string, string>
     */
    public function migrateMapping(): array
    {
        $mapping = [];

        foreach ($this->originalColumnNames as $oldName) {
            if (isset($this->renames[$oldName])) {
                $newName = $this->renames[$oldName];

                if (isset($this->columns[$newName])) {
                    $mapping[$oldName] = $newName;
                }

                continue;
            }

            if (isset($this->columns[$oldName])) {
                $mapping[$oldName] = $oldName;
            }
        }

        return $mapping;
    }
}
