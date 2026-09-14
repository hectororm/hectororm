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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Generator\Sqlite\CreateTableParser;
use Hector\Schema\Index;
use Hector\Schema\Plan\Generated;
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
    /** @var array<string, string> current column name => original source name */
    private array $sources;

    /** @var string[] column names as they existed before any operation */
    private array $originalColumnNames;

    /**
     * @param array<string, ColumnDef>     $columns
     * @param array<string, IndexDef>      $indexes
     * @param array<string, ForeignKeyDef> $foreignKeys
     */
    public function __construct(
        private array $columns,
        private array $indexes,
        private array $foreignKeys,
        private ?string $tableName = null,
    ) {
        $this->originalColumnNames = array_map('strval', array_keys($this->columns));
        $this->sources = array_combine($this->originalColumnNames, $this->originalColumnNames);
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
        unset($this->sources[$column->name]);
    }

    private function modifyColumn(ModifyColumn $operation): void
    {
        $name = $this->resolveColumnName($operation->getName());
        if (null === $name) {
            return;
        }

        $this->columns[$name] = ColumnDef::fromOperation($operation)->withName($name);
    }

    private function dropColumn(string $name): void
    {
        $name = $this->resolveColumnName($name);
        if (null === $name) {
            return;
        }

        unset($this->columns[$name]);
        unset($this->sources[$name]);
    }

    private function renameColumn(string $oldName, string $newName): void
    {
        $oldName = $this->resolveColumnName($oldName);
        if (null === $oldName) {
            return;
        }

        if ($oldName === $newName) {
            return;
        }

        $existing = $this->resolveColumnName($newName);
        if (null !== $existing && $existing !== $oldName) {
            throw new PlanException(sprintf('Cannot rename column "%s" to existing column "%s"', $oldName, $newName));
        }

        // Preserve declaration order and follow rename chains back to the original data.
        $columns = [];
        $parser = new CreateTableParser();
        foreach ($this->columns as $column) {
            $name = $column->name;
            $targetName = $name === $oldName ? $newName : $name;
            $column = $column->withName($targetName);
            if (null !== $column->generated) {
                $column->generated = new Generated(
                    $parser->renameColumnReferences($column->generated->getExpression(), $oldName, $newName),
                    $column->generated->isStored(),
                );
            }
            $columns[$targetName] = $column;
        }
        $this->columns = $columns;

        if (isset($this->sources[$oldName])) {
            $this->sources[$newName] = $this->sources[$oldName];
            unset($this->sources[$oldName]);
        }

        foreach ($this->indexes as $name => $index) {
            $index = clone $index;
            $index->columns = $this->renameReferences($index->columns, $oldName, $newName);
            $this->indexes[$name] = $index;
        }

        foreach ($this->foreignKeys as $name => $foreignKey) {
            $foreignKey = clone $foreignKey;
            $foreignKey->columns = $this->renameReferences($foreignKey->columns, $oldName, $newName);
            if (null !== $this->tableName && 0 === strcasecmp($foreignKey->referencedTable, $this->tableName)) {
                $foreignKey->referencedColumns = $this->renameReferences(
                    $foreignKey->referencedColumns,
                    $oldName,
                    $newName,
                );
            }
            $this->foreignKeys[$name] = $foreignKey;
        }
    }

    private function resolveColumnName(string $name): ?string
    {
        foreach ($this->columns as $column) {
            if (0 === strcasecmp($column->name, $name)) {
                return $column->name;
            }
        }

        return null;
    }

    /**
     * @param string[] $names
     * @return string[]
     */
    private function renameReferences(array $names, string $oldName, string $newName): array
    {
        return array_map(
            static fn(string $name): string => 0 === strcasecmp($name, $oldName) ? $newName : $name,
            $names,
        );
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
     * Generated targets are omitted so SQLite recalculates their values.
     *
     * @return array<string, string>
     */
    public function migrateMapping(): array
    {
        $mapping = [];
        $targets = array_flip($this->sources);

        foreach ($this->originalColumnNames as $oldName) {
            if (false === isset($targets[$oldName])) {
                continue;
            }

            $newName = (string)$targets[$oldName];
            if (null !== $this->columns[$newName]->generated) {
                continue;
            }

            $mapping[$oldName] = $newName;
        }

        return $mapping;
    }
}
