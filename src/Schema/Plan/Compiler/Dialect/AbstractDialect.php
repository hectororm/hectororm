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

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropTrigger;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Raw;

/**
 * Base dialect: DBMS-agnostic SQL shared by all concrete dialects.
 *
 * Holds identifier quoting, the statements whose syntax is portable
 * (DROP TABLE/VIEW/TRIGGER, MIGRATE DATA), the DEFAULT and FOREIGN KEY
 * definition fragments, and the ALTER validation. Concrete dialects
 * implement the DBMS-specific statements declared by DialectInterface.
 */
abstract class AbstractDialect implements DialectInterface
{
    public function __construct(
        protected ?DriverCapabilities $capabilities = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supportsAlterForeignKey(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function inlinesCreateTableForeignKeys(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function compileDropTable(DropTable $dropTable): string
    {
        return sprintf(
            'DROP TABLE %s%s',
            $dropTable->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($dropTable->getObjectName()),
        );
    }

    /**
     * @inheritDoc
     */
    public function compileDropView(DropView $dropView): string
    {
        return sprintf(
            'DROP VIEW %s%s',
            $dropView->ifExists() ? 'IF EXISTS ' : '',
            $this->quoteIdentifier($dropView->getObjectName()),
        );
    }

    /**
     * @inheritDoc
     */
    public function compileDropTrigger(DropTrigger $trigger): string
    {
        return sprintf(
            'DROP TRIGGER IF EXISTS %s',
            $this->quoteIdentifier($trigger->getName()),
        );
    }

    /**
     * @inheritDoc
     */
    public function compileMigrateData(MigrateData $migrateData): string
    {
        $mapping = $migrateData->getColumnMapping();

        if ([] === $mapping) {
            return sprintf(
                'INSERT INTO %s SELECT * FROM %s',
                $this->quoteIdentifier($migrateData->getTargetTable()),
                $this->quoteIdentifier($migrateData->getObjectName()),
            );
        }

        $targetColumns = $this->quoteIdentifiers(array_values($mapping));
        $sourceColumns = $this->quoteIdentifiers(array_keys($mapping));

        return sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s',
            $this->quoteIdentifier($migrateData->getTargetTable()),
            $targetColumns,
            $sourceColumns,
            $this->quoteIdentifier($migrateData->getObjectName()),
        );
    }

    /**
     * @inheritDoc
     */
    public function compileAddForeignKey(AddForeignKey $operation): string
    {
        return sprintf(
            'ALTER TABLE %s ADD %s',
            $this->quoteIdentifier($operation->getObjectName()),
            $this->compileForeignKeyDefinition($operation),
        );
    }

    /**
     * @inheritDoc
     */
    public function compileDropForeignKey(DropForeignKey $operation): string
    {
        return sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->quoteIdentifier($operation->getObjectName()),
            $this->quoteIdentifier($operation->getName()),
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
        return implode(', ', array_map(fn(string $name): string => $this->quoteIdentifier($name), $names));
    }

    /**
     * Validate ALTER TABLE operations.
     *
     * Detects operations that are impossible without a database change,
     * and throws a PlanException before any SQL is generated.
     *
     * @param AlterTable $alterTable
     *
     * @throws PlanException
     */
    protected function validateAlterOperations(AlterTable $alterTable): void
    {
        foreach ($alterTable as $operation) {
            if (false === ($operation instanceof AddColumn)) {
                continue;
            }

            if ($operation->isNullable() || $operation->hasDefault() || $operation->isAutoIncrement()) {
                continue;
            }

            throw new PlanException(
                sprintf(
                    'Cannot add NOT NULL column \'%s\' without a default value on an existing table \'%s\'',
                    $operation->getName(),
                    $alterTable->getObjectName(),
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

        if ($default instanceof Raw) {
            return sprintf(' DEFAULT %s', $default->getExpression());
        }

        if (null === $default) {
            return ' DEFAULT NULL';
        }

        if (is_bool($default)) {
            return sprintf(' DEFAULT %s', $default ? '1' : '0');
        }

        if (is_int($default) || is_float($default)) {
            return sprintf(' DEFAULT %s', $default);
        }

        /** @var string $stringDefault */
        $stringDefault = $default;

        return sprintf(' DEFAULT \'%s\'', str_replace("'", "''", $stringDefault));
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
