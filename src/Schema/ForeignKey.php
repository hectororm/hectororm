<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema;

use Generator;
use Hector\Schema\Exception\SchemaException;

/**
 * Class ForeignKey.
 */
class ForeignKey
{
    use NameHelperTrait;

    public const RULE_CASCADE = 'CASCADE';
    public const RULE_NO_ACTION = 'NO ACTION';
    public const RULE_RESTRICT = 'RESTRICT';
    public const RULE_SET_NULL = 'SET NULL';

    public function __construct(
        private string $name,
        private array $columns_name,
        private string $referenced_schema_name,
        private string $referenced_table_name,
        private array $referenced_columns_name,
        private string $update_rule = self::RULE_NO_ACTION,
        private string $delete_rule = self::RULE_NO_ACTION,
        private ?Table $table = null,
    ) {
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'columns_name' => $this->columns_name,
            'referenced_schema_name' => $this->referenced_schema_name,
            'referenced_table_name' => $this->referenced_table_name,
            'referenced_columns_name' => $this->referenced_columns_name,
            'update_rule' => $this->update_rule,
            'delete_rule' => $this->delete_rule,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'];
        $this->columns_name = $data['columns_name'];
        $this->referenced_schema_name = $data['referenced_schema_name'];
        $this->referenced_table_name = $data['referenced_table_name'];
        $this->referenced_columns_name = $data['referenced_columns_name'];
        $this->update_rule = $data['update_rule'];
        $this->delete_rule = $data['delete_rule'];
        $this->table = null;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get columns name.
     *
     * @param bool $quoted
     * @param string|null $tableAlias
     *
     * @return string[]
     */
    public function getColumnsName(bool $quoted = false, ?string $tableAlias = null): array
    {
        $names = $this->columns_name;

        $quoted && $names = $this->quoteNames($names);
        null !== $tableAlias && $names = $this->addAliasToNames($names, $tableAlias);

        return $names;
    }

    /**
     * Get referenced schema name.
     *
     * @return string
     */
    public function getReferencedSchemaName(): string
    {
        return $this->referenced_schema_name;
    }

    /**
     * Get referenced table name.
     *
     * @return string
     */
    public function getReferencedTableName(): string
    {
        return $this->referenced_table_name;
    }

    /**
     * Get referenced columns name.
     *
     * @param bool $quoted
     * @param string|null $tableAlias
     *
     * @return string[]
     */
    public function getReferencedColumnsName(bool $quoted = false, ?string $tableAlias = null): array
    {
        $names = $this->referenced_columns_name;

        $quoted && $names = $this->quoteNames($names);
        null !== $tableAlias && $names = $this->addAliasToNames($names, $tableAlias);

        return $names;
    }

    /**
     * Get update rule.
     *
     * @return string
     */
    public function getUpdateRule(): string
    {
        return $this->update_rule;
    }

    /**
     * Get delete rule.
     *
     * @return string
     */
    public function getDeleteRule(): string
    {
        return $this->delete_rule;
    }

    /**
     * Get table.
     *
     * @return Table
     * @throws SchemaException
     */
    public function getTable(): Table
    {
        return $this->table ?? throw new SchemaException('No table attached to the foreign key');
    }

    /**
     * Set table.
     *
     * @param Table|null $table
     */
    public function setTable(?Table $table): void
    {
        $this->table = $table;
    }

    /**
     * Get columns.
     *
     * @return Generator<Column>
     * @throws SchemaException
     */
    public function getColumns(): Generator
    {
        $table = $this->getTable();

        /** @var Column $column */
        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $this->columns_name)) {
                yield $column;
            }
        }
    }

    /**
     * Get referenced schema.
     *
     * @return Table|null
     * @throws SchemaException
     */
    public function getReferencedTable(): ?Table
    {
        return
            $this
                ->getTable()->getSchema()?->getContainer()
                ->getSchema($this->getReferencedSchemaName())->getTable($this->getReferencedTableName());
    }

    /**
     * Get referenced columns.
     *
     * @return Generator<Column>
     * @throws SchemaException
     */
    public function getReferencedColumns(): Generator
    {
        $table = $this->getReferencedTable();
        if (null === $table) {
            return;
        }

        /** @var Column $column */
        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $this->referenced_columns_name)) {
                yield $column;
            }
        }
    }
}
