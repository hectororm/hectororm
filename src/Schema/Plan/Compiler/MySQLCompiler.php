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

namespace Hector\Schema\Plan\Compiler;

use Hector\Schema\Index;
use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\AlterView;
use Hector\Schema\Plan\Operation\CreateTable;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\OperationInterface;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Schema;

class MySQLCompiler extends AbstractCompiler
{
    /**
     * @inheritDoc
     */
    protected function getIdentifierQuote(): string
    {
        return '`';
    }

    /**
     * @inheritDoc
     */
    protected function compileCreateTable(ObjectPlan $objectPlan): array
    {
        $operations = $objectPlan->getArrayCopy();
        $createTable = $operations[0];
        assert($createTable instanceof CreateTable);

        $tableName = $this->quoteIdentifier($createTable->getObjectName());
        $remaining = array_slice($operations, 1);

        // Column definitions first
        $definitions = array_map(
            fn(AddColumn $op) => $this->compileColumnDefinition($op),
            array_filter($remaining, fn($op) => $op instanceof AddColumn),
        );

        // Indexes and foreign keys after columns
        array_push($definitions, ...array_map(
            fn(AddIndex $op) => $this->compileIndexDefinition($op),
            array_filter($remaining, fn($op) => $op instanceof AddIndex),
        ));
        array_push($definitions, ...array_map(
            fn(AddForeignKey $op) => $this->compileForeignKeyDefinition($op),
            array_filter($remaining, fn($op) => $op instanceof AddForeignKey),
        ));

        if (empty($definitions)) {
            return [];
        }

        $sql = sprintf(
            "CREATE TABLE %s%s (\n  %s\n)",
            $createTable->ifNotExists() ? 'IF NOT EXISTS ' : '',
            $tableName,
            implode(",\n  ", $definitions),
        );

        // Table options
        $options = [];
        if (null !== $createTable->getCharset()) {
            $options[] = sprintf('DEFAULT CHARSET=%s', $createTable->getCharset());
        }
        if (null !== $createTable->getCollation()) {
            $options[] = sprintf('COLLATE=%s', $createTable->getCollation());
        }
        if (false === empty($options)) {
            $sql .= ' ' . implode(' ', $options);
        }

        return [$sql];
    }

    /**
     * @inheritDoc
     */
    protected function compileRenameTable(RenameTable $operation): string
    {
        return sprintf(
            'RENAME TABLE %s TO %s',
            $this->quoteIdentifier($operation->getObjectName()),
            $this->quoteIdentifier($operation->getNewName()),
        );
    }

    /**
     * @inheritDoc
     */
    protected function compileAlterTable(ObjectPlan $objectPlan, ?Schema $schema = null): array
    {
        $tableName = $this->quoteIdentifier($objectPlan->getName());

        // Compile each operation into a clause (string, string[] or null)
        $clauses = array_filter(array_map(
            fn(OperationInterface $op) => $this->compileAlterClause($op, $objectPlan->getName(), $schema),
            $objectPlan->getArrayCopy(),
        ));

        if (empty($clauses)) {
            return [];
        }

        // Flatten: each clause can be a string or string[]
        $clauses = array_merge(...array_map(fn(string|array $clause) => (array)$clause, $clauses));

        return [sprintf('ALTER TABLE %s %s', $tableName, implode(', ', $clauses))];
    }

    /**
     * Compile an individual ALTER TABLE clause.
     *
     * @param OperationInterface $operation
     * @param string $tableName
     * @param Schema|null $schema
     *
     * @return string|string[]|null
     */
    protected function compileAlterClause(
        OperationInterface $operation,
        string $tableName,
        ?Schema $schema
    ): string|array|null {
        return match ($operation::class) {
            AddColumn::class => sprintf(
                'ADD COLUMN %s%s',
                $this->compileColumnDefinition($operation),
                $this->compileColumnPlacement($operation),
            ),
            DropColumn::class => sprintf(
                'DROP COLUMN %s',
                $this->quoteIdentifier($operation->getName()),
            ),
            ModifyColumn::class => sprintf(
                'MODIFY COLUMN %s%s',
                $this->compileColumnDefinition($operation),
                $this->compileColumnPlacement($operation),
            ),
            RenameColumn::class => sprintf(
                'RENAME COLUMN %s TO %s',
                $this->quoteIdentifier($operation->getName()),
                $this->quoteIdentifier($operation->getNewName()),
            ),
            AddIndex::class => $this->compileAlterAddIndex($operation, $tableName, $schema),
            DropIndex::class => sprintf(
                'DROP INDEX %s',
                $this->quoteIdentifier($operation->getName()),
            ),
            AddForeignKey::class => sprintf(
                'ADD %s',
                $this->compileForeignKeyDefinition($operation),
            ),
            DropForeignKey::class => sprintf(
                'DROP FOREIGN KEY %s',
                $this->quoteIdentifier($operation->getName()),
            ),
            default => null,
        };
    }

    /**
     * Compile an AddIndex clause for ALTER TABLE.
     *
     * When a schema is available, checks if the index already exists and emits
     * a DROP INDEX + ADD INDEX pair. Otherwise, emits only ADD INDEX.
     *
     * @param AddIndex $operation
     * @param string $tableName
     * @param Schema|null $schema
     *
     * @return string|string[]
     */
    protected function compileAlterAddIndex(AddIndex $operation, string $tableName, ?Schema $schema): string|array
    {
        $indexExists = false;

        if (null !== $schema && $schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);

            foreach ($table->getIndexes() as $index) {
                if ($index->getName() === $operation->getName()) {
                    $indexExists = true;
                    break;
                }
            }
        }

        if (true === $indexExists) {
            return [
                sprintf('DROP INDEX %s', $this->quoteIdentifier($operation->getName())),
                sprintf('ADD %s', $this->compileIndexDefinition($operation)),
            ];
        }

        return sprintf('ADD %s', $this->compileIndexDefinition($operation));
    }

    /**
     * @inheritDoc
     */
    protected function compileColumnDefinition(AddColumn|ModifyColumn $operation): string
    {
        $parts = [
            $this->quoteIdentifier($operation->getName()),
            $operation->getType(),
        ];

        $parts[] = $operation->isNullable() ? 'NULL' : 'NOT NULL';

        $default = $this->compileDefault($operation);
        if ('' !== $default) {
            $parts[] = trim($default);
        }

        if (true === $operation->isAutoIncrement()) {
            $parts[] = 'AUTO_INCREMENT';
        }

        return implode(' ', $parts);
    }

    /**
     * Compile column placement (AFTER/FIRST).
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    protected function compileColumnPlacement(AddColumn|ModifyColumn $operation): string
    {
        if (true === $operation->isFirst()) {
            return ' FIRST';
        }

        if (null !== $operation->getAfter()) {
            return sprintf(' AFTER %s', $this->quoteIdentifier($operation->getAfter()));
        }

        return '';
    }

    /**
     * Compile an index definition fragment (for CREATE TABLE or ALTER TABLE).
     *
     * @param AddIndex $operation
     *
     * @return string
     */
    protected function compileIndexDefinition(AddIndex $operation): string
    {
        $columns = $this->quoteIdentifiers($operation->getColumns());

        return match ($operation->getType()) {
            Index::PRIMARY => sprintf('PRIMARY KEY (%s)', $columns),
            Index::UNIQUE => sprintf('UNIQUE INDEX %s (%s)', $this->quoteIdentifier($operation->getName()), $columns),
            default => sprintf('INDEX %s (%s)', $this->quoteIdentifier($operation->getName()), $columns),
        };
    }

    /**
     * @inheritDoc
     */
    protected function compileCreateView(CreateView $operation): array
    {
        $sql = 'CREATE';

        if (true === $operation->orReplace()) {
            $sql .= ' OR REPLACE';
        }

        if (null !== $operation->getAlgorithm()) {
            $sql .= sprintf(' ALGORITHM = %s', strtoupper($operation->getAlgorithm()));
        }

        $sql .= sprintf(' VIEW %s AS %s', $this->quoteIdentifier($operation->getObjectName()),
            $operation->getStatement());

        return [$sql];
    }

    /**
     * @inheritDoc
     */
    protected function compileAlterView(AlterView $operation): array
    {
        $sql = 'ALTER';

        if (null !== $operation->getAlgorithm()) {
            $sql .= sprintf(' ALGORITHM = %s', strtoupper($operation->getAlgorithm()));
        }

        $sql .= sprintf(' VIEW %s AS %s', $this->quoteIdentifier($operation->getObjectName()),
            $operation->getStatement());

        return [$sql];
    }
}
