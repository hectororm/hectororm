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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AlterView;
use Hector\Schema\Plan\Operation\CreateTable;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropTable;
use Hector\Schema\Plan\Operation\DropView;
use Hector\Schema\Plan\Operation\MigrateData;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameTable;
use Hector\Schema\Schema;

abstract class AbstractCompiler implements CompilerInterface
{
    /**
     * @inheritDoc
     */
    public function compile(ObjectPlan $objectPlan, ?Schema $schema = null): iterable
    {
        $operations = $objectPlan->getArrayCopy();

        if (empty($operations)) {
            return [];
        }

        // Handle single-operation plans (CREATE, DROP, RENAME, MIGRATE)
        $first = $operations[0];
        $result = match ($first::class) {
            // Table plan
            CreateTable::class => $this->compileCreateTable($objectPlan),
            DropTable::class => [$this->compileDropTable($first)],
            RenameTable::class => [$this->compileRenameTable($first)],
            MigrateData::class => [$this->compileMigrateData($first)],
            // View plan
            CreateView::class => $this->compileCreateView($first),
            DropView::class => [$this->compileDropView($first)],
            AlterView::class => $this->compileAlterView($first),
            default => null,
        };

        if (null !== $result) {
            return $result;
        }

        // Validate ALTER TABLE operations
        $this->validateAlterOperations($objectPlan);

        // Handle ALTER TABLE (grouped operations)
        return $this->compileAlterTable($objectPlan, $schema);
    }

    /**
     * Compile a CREATE TABLE statement with all its columns, indexes and foreign keys.
     *
     * @param ObjectPlan $objectPlan
     *
     * @return string[]
     */
    abstract protected function compileCreateTable(ObjectPlan $objectPlan): array;

    /**
     * Compile a DROP TABLE statement.
     *
     * @param DropTable $operation
     *
     * @return string
     */
    protected function compileDropTable(DropTable $operation): string
    {
        return sprintf(
            'DROP TABLE %s%s',
            $operation->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($operation->getObjectName()),
        );
    }

    /**
     * Compile a RENAME TABLE statement.
     *
     * @param RenameTable $operation
     *
     * @return string
     */
    abstract protected function compileRenameTable(RenameTable $operation): string;

    /**
     * Compile ALTER TABLE statements from an object plan.
     *
     * @param ObjectPlan $objectPlan
     * @param Schema|null $schema
     *
     * @return string[]
     */
    abstract protected function compileAlterTable(ObjectPlan $objectPlan, ?Schema $schema = null): array;

    /**
     * Compile a CREATE VIEW statement.
     *
     * @param CreateView $operation
     *
     * @return string[]
     */
    abstract protected function compileCreateView(CreateView $operation): array;

    /**
     * Compile a DROP VIEW statement.
     *
     * @param DropView $operation
     *
     * @return string
     */
    protected function compileDropView(DropView $operation): string
    {
        return sprintf(
            'DROP VIEW %s%s',
            $operation->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($operation->getObjectName()),
        );
    }

    /**
     * Compile an ALTER VIEW statement.
     *
     * @param AlterView $operation
     *
     * @return string[]
     */
    abstract protected function compileAlterView(AlterView $operation): array;

    /**
     * Compile a column definition fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    abstract protected function compileColumnDefinition(AddColumn|ModifyColumn $operation): string;

    /**
     * Compile a MIGRATE DATA statement (INSERT INTO ... SELECT ...).
     *
     * @param MigrateData $operation
     *
     * @return string
     */
    protected function compileMigrateData(MigrateData $operation): string
    {
        $mapping = $operation->getColumnMapping();

        if (empty($mapping)) {
            return sprintf(
                'INSERT INTO %s SELECT * FROM %s',
                $this->quoteIdentifier($operation->getTargetTable()),
                $this->quoteIdentifier($operation->getObjectName()),
            );
        }

        $targetColumns = $this->quoteIdentifiers(array_values($mapping));
        $sourceColumns = $this->quoteIdentifiers(array_keys($mapping));

        return sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $this->quoteIdentifier($operation->getTargetTable()),
            $targetColumns,
            $sourceColumns,
            $this->quoteIdentifier($operation->getObjectName()),
        );
    }

    /**
     * Get the identifier quote character for this dialect.
     *
     * @return string
     */
    abstract protected function getIdentifierQuote(): string;

    /**
     * Quote an identifier.
     *
     * @param string $name
     *
     * @return string
     */
    protected function quoteIdentifier(string $name): string
    {
        $quote = $this->getIdentifierQuote();

        return sprintf('%1$s%2$s%1$s', $quote, str_replace($quote, $quote . $quote, $name));
    }

    /**
     * Quote a list of identifiers.
     *
     * @param string[] $names
     *
     * @return string
     */
    protected function quoteIdentifiers(array $names): string
    {
        return implode(', ', array_map(fn(string $name) => $this->quoteIdentifier($name), $names));
    }

    /**
     * Validate ALTER TABLE operations.
     *
     * Detects operations that are impossible without a database change,
     * and throws a PlanException before any SQL is generated.
     *
     * @param ObjectPlan $objectPlan
     *
     * @throws PlanException
     */
    protected function validateAlterOperations(ObjectPlan $objectPlan): void
    {
        foreach ($objectPlan as $operation) {
            if (false === $operation instanceof AddColumn) {
                continue;
            }

            if ($operation->isNullable() || $operation->hasDefault() || $operation->isAutoIncrement()) {
                continue;
            }

            throw new PlanException(
                sprintf(
                    'Cannot add NOT NULL column \'%s\' without a default value on an existing table \'%s\'',
                    $operation->getName(),
                    $objectPlan->getName(),
                )
            );
        }
    }

    /**
     * Compile the default value fragment.
     *
     * @param AddColumn|ModifyColumn $operation
     *
     * @return string
     */
    protected function compileDefault(AddColumn|ModifyColumn $operation): string
    {
        if (false === $operation->hasDefault()) {
            return '';
        }

        $default = $operation->getDefault();

        if (null === $default) {
            return ' DEFAULT NULL';
        }

        if (is_bool($default)) {
            return sprintf(' DEFAULT %s', $default ? '1' : '0');
        }

        if (is_int($default) || is_float($default)) {
            return sprintf(' DEFAULT %s', $default);
        }

        return sprintf(' DEFAULT \'%s\'', str_replace("'", "''", (string)$default));
    }

    /**
     * Compile a foreign key definition fragment.
     *
     * @param AddForeignKey $operation
     *
     * @return string
     */
    protected function compileForeignKeyDefinition(AddForeignKey $operation): string
    {
        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quoteIdentifier($operation->getName()),
            $this->quoteIdentifiers($operation->getColumns()),
            $this->quoteIdentifier($operation->getReferencedTable()),
            $this->quoteIdentifiers($operation->getReferencedColumns()),
        );

        if ('NO ACTION' !== $operation->getOnUpdate()) {
            $sql .= sprintf(' ON UPDATE %s', $operation->getOnUpdate());
        }

        if ('NO ACTION' !== $operation->getOnDelete()) {
            $sql .= sprintf(' ON DELETE %s', $operation->getOnDelete());
        }

        return $sql;
    }
}
