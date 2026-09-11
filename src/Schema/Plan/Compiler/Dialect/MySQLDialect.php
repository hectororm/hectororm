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

namespace Hector\Schema\Plan\Compiler\Dialect;

use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyCharset;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Plan\OperationInterface;
use Hector\Schema\Plan\PurgeTable;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Schema;

/**
 * MySQL / MariaDB / Vitess dialect.
 */
final class MySQLDialect extends AbstractDialect
{
    /**
     * @inheritDoc
     */
    public function compilePurgeTable(PurgeTable $purgeTable): iterable
    {
        yield sprintf(
            '%s %s',
            true === $purgeTable->resetIncrement() ? 'TRUNCATE TABLE' : 'DELETE FROM',
            $this->quoteIdentifier($purgeTable->getObjectName()),
        );
    }

    /**
     * @inheritDoc
     */
    public function driverNames(): array
    {
        return ['mysql', 'mariadb', 'vitess'];
    }

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
    public function compileStandaloneOperation(OperationInterface $operation): iterable
    {
        $tableName = $operation->getObjectName();

        if (null === $tableName) {
            return [];
        }

        $clauses = $this->compileAlterClause($operation, $tableName, null);

        if ([] === $clauses) {
            return [];
        }

        $quotedTable = $this->quoteIdentifier($tableName);

        return array_map(
            fn(string $c): string => sprintf('ALTER TABLE %s %s', $quotedTable, $c),
            $clauses,
        );
    }

    /**
     * @inheritDoc
     */
    public function compileDisableForeignKeyChecks(): string
    {
        return 'SET FOREIGN_KEY_CHECKS = 0';
    }

    /**
     * @inheritDoc
     */
    public function compileEnableForeignKeyChecks(): string
    {
        return 'SET FOREIGN_KEY_CHECKS = 1';
    }

    /**
     * @inheritDoc
     */
    public function compileCreateTable(CreateTable $createTable): iterable
    {
        $tableName = $this->quoteIdentifier($createTable->getObjectName());
        $operations = $createTable->getArrayCopy();

        // Column definitions first
        $definitions = array_map(
            fn(AddColumn $op): string => $this->compileColumnDefinition($op),
            array_filter($operations, fn($op): bool => $op instanceof AddColumn),
        );

        // Indexes after columns (FK excluded — handled by Post pass)
        array_push($definitions, ...array_map(
            fn(AddIndex $op): string => $this->compileIndexDefinition($op),
            array_filter($operations, fn($op): bool => $op instanceof AddIndex),
        ));

        if ([] === $definitions) {
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
        if ([] !== $options) {
            $sql .= ' ' . implode(' ', $options);
        }

        return [$sql];
    }

    /**
     * @inheritDoc
     */
    public function compileAlterTable(AlterTable $alterTable, CompilationContext $context): iterable
    {
        // Validate operations
        $this->validateAlterOperations($alterTable);

        $tableName = $this->quoteIdentifier($alterTable->getObjectName());

        // Filter out Pre/Post operations (handled in passes 1 and 3)
        // and RenameTable (emitted separately after other clauses)
        $clauses = [];
        $renameOperation = null;

        foreach ($alterTable as $operation) {
            if ($operation instanceof PreOperationInterface ||
                $operation instanceof PostOperationInterface) {
                continue;
            }

            if ($operation instanceof RenameTable) {
                $renameOperation = $operation;
                continue;
            }

            array_push($clauses, ...$this->compileAlterClause($operation, $alterTable->getObjectName(), $context->schema));
        }

        if ([] !== $clauses) {
            yield sprintf('ALTER TABLE %s %s', $tableName, implode(', ', $clauses));
        }

        // Rename must be emitted as a separate statement (cannot be combined with other clauses)
        if (null !== $renameOperation) {
            yield sprintf(
                'ALTER TABLE %s RENAME TO %s',
                $tableName,
                $this->quoteIdentifier($renameOperation->getNewName()),
            );
        }
    }

    /**
     * Compile individual ALTER TABLE clauses for a single operation.
     *
     * Always returns a list of clause strings (possibly empty), so the caller
     * can simply concatenate the results without shape juggling.
     *
     * @param OperationInterface $operation
     * @param string $tableName
     * @param Schema|null $schema
     *
     * @return string[]
     */
    private function compileAlterClause(
        OperationInterface $operation,
        string $tableName,
        ?Schema $schema
    ): array {
        return match ($operation::class) {
            AddColumn::class => [
                sprintf(
                    'ADD COLUMN %s%s',
                    $this->compileColumnDefinition($operation),
                    $this->compileColumnPlacement($operation),
                ),
            ],
            DropColumn::class => [
                sprintf('DROP COLUMN %s', $this->quoteIdentifier($operation->getName())),
            ],
            ModifyColumn::class => [
                sprintf(
                    'MODIFY COLUMN %s%s',
                    $this->compileColumnDefinition($operation),
                    $this->compileColumnPlacement($operation),
                ),
            ],
            RenameColumn::class => [$this->compileAlterRenameColumn($operation, $tableName, $schema)],
            AddIndex::class => $this->compileAlterAddIndex($operation, $tableName, $schema),
            DropIndex::class => [
                sprintf('DROP INDEX %s', $this->quoteIdentifier($operation->getName())),
            ],
            ModifyCharset::class => [$this->compileAlterModifyCharset($operation)],
            default => [],
        };
    }

    /**
     * Compile a ModifyCharset clause for ALTER TABLE.
     *
     * @param ModifyCharset $operation
     *
     * @return string
     */
    private function compileAlterModifyCharset(ModifyCharset $operation): string
    {
        $sql = sprintf('DEFAULT CHARSET=%s', $operation->getCharset());

        if (null !== $operation->getCollation()) {
            $sql .= sprintf(' COLLATE=%s', $operation->getCollation());
        }

        return $sql;
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
     * @return string[]
     */
    private function compileAlterAddIndex(AddIndex $operation, string $tableName, ?Schema $schema): array
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

        return [sprintf('ADD %s', $this->compileIndexDefinition($operation))];
    }

    /**
     * Compile a RENAME COLUMN clause for ALTER TABLE.
     *
     * On servers that support RENAME COLUMN (MySQL >= 8.0, MariaDB >= 10.5.2),
     * emits `RENAME COLUMN old TO new`. On older servers, falls back to
     * `CHANGE COLUMN old new <full definition>` which requires a Schema to
     * introspect the current column definition.
     *
     * @param RenameColumn $operation
     * @param string $tableName
     * @param Schema|null $schema
     *
     * @return string
     * @throws PlanException
     */
    private function compileAlterRenameColumn(
        RenameColumn $operation,
        string $tableName,
        ?Schema $schema
    ): string {
        // Modern syntax: RENAME COLUMN (default when no capabilities or capable server)
        if (null === $this->capabilities || true === $this->capabilities->hasRenameColumn()) {
            return sprintf(
                'RENAME COLUMN %s TO %s',
                $this->quoteIdentifier($operation->getName()),
                $this->quoteIdentifier($operation->getNewName()),
            );
        }

        // Legacy syntax: CHANGE COLUMN old new <definition> (requires schema)
        if (null === $schema || false === $schema->hasTable($tableName)) {
            throw new PlanException(
                sprintf(
                    'Cannot rename column "%s" on table "%s": ' .
                    'the server does not support RENAME COLUMN and no schema was provided ' .
                    'to generate a CHANGE COLUMN statement',
                    $operation->getName(),
                    $tableName,
                )
            );
        }

        $table = $schema->getTable($tableName);
        $column = $table->getColumn($operation->getName());

        return sprintf(
            'CHANGE COLUMN %s %s',
            $this->quoteIdentifier($operation->getName()),
            $this->compileSchemaColumnDefinition($column, $operation->getNewName()),
        );
    }

    /**
     * Compile a column definition from a Schema Column object.
     *
     * Used by CHANGE COLUMN to reproduce the current column definition
     * with a new name.
     *
     * @param Column $column
     * @param string $newName
     *
     * @return string
     */
    private function compileSchemaColumnDefinition(Column $column, string $newName): string
    {
        $type = $column->getType();

        if (null !== $column->getMaxlength()) {
            $type .= '(' . $column->getMaxlength() . ')';
        } elseif (null !== $column->getNumericPrecision()) {
            $type .= '(' . $column->getNumericPrecision();

            if (null !== $column->getNumericScale()) {
                $type .= ',' . $column->getNumericScale();
            }

            $type .= ')';
        }

        if (true === $column->isUnsigned()) {
            $type .= ' unsigned';
        }

        $parts = [
            $this->quoteIdentifier($newName),
            $type,
        ];

        $parts[] = $column->isNullable() ? 'NULL' : 'NOT NULL';

        /** @var Raw|string|int|float|null $default */
        $default = $column->getDefault();

        $defaultClause = match (true) {
            $default instanceof Raw => sprintf('DEFAULT %s', $default->getExpression()),
            null !== $default && is_numeric($default) => sprintf('DEFAULT %s', $default),
            null !== $default => sprintf('DEFAULT \'%s\'', str_replace("'", "''", $default)),
            $column->isNullable() => 'DEFAULT NULL',
            default => null,
        };

        if (null !== $defaultClause) {
            $parts[] = $defaultClause;
        }

        if (true === $column->isAutoIncrement()) {
            $parts[] = 'AUTO_INCREMENT';
        }

        return implode(' ', $parts);
    }

    /**
     * Compile a column definition fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    private function compileColumnDefinition(AddColumn|ModifyColumn $operation): string
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
    private function compileColumnPlacement(AddColumn|ModifyColumn $operation): string
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
    private function compileIndexDefinition(AddIndex $operation): string
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
    public function compileCreateView(CreateView $createView): iterable
    {
        $sql = 'CREATE';

        if (true === $createView->orReplace()) {
            $sql .= ' OR REPLACE';
        }

        if (null !== $createView->getAlgorithm()) {
            $sql .= sprintf(' ALGORITHM = %s', strtoupper($createView->getAlgorithm()));
        }

        $sql .= sprintf(
            ' VIEW %s AS %s',
            $this->quoteIdentifier($createView->getObjectName()),
            $createView->getStatement(),
        );

        return [$sql];
    }

    /**
     * @inheritDoc
     */
    public function compileAlterView(AlterView $alterView): iterable
    {
        $sql = 'ALTER';

        if (null !== $alterView->getAlgorithm()) {
            $sql .= sprintf(' ALGORITHM = %s', strtoupper($alterView->getAlgorithm()));
        }

        $sql .= sprintf(
            ' VIEW %s AS %s',
            $this->quoteIdentifier($alterView->getObjectName()),
            $alterView->getStatement(),
        );

        return [$sql];
    }

    /**
     * @inheritDoc
     */
    public function compileCreateTrigger(CreateTrigger $trigger): string
    {
        return sprintf(
            'CREATE TRIGGER %s %s %s ON %s FOR EACH ROW %s',
            $this->quoteIdentifier($trigger->getName()),
            $trigger->getTiming(),
            $trigger->getEvent(),
            $this->quoteIdentifier($trigger->getObjectName()),
            $trigger->getBody(),
        );
    }
}
