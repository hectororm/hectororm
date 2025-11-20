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

use Hector\Schema\Exception\SchemaException;

/**
 * Class Index.
 */
class Index
{
    use NameHelperTrait;

    public const PRIMARY = 'primary';
    public const UNIQUE = 'unique';
    public const INDEX = 'index';

    public function __construct(
        private string $name,
        private string $type,
        private array $columns_name = [],
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
            'type' => $this->type,
            'columns_name' => $this->columns_name,
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
        $this->type = $data['type'];
        $this->columns_name = $data['columns_name'];
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
     * Get type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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
     * Get table.
     *
     * @return Table
     * @throws SchemaException
     */
    public function getTable(): Table
    {
        return $this->table ?? throw new SchemaException('No table attached to the index');
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
     * @return Column[]
     * @throws SchemaException
     */
    public function getColumns(): array
    {
        $columnsPosition = array_flip($this->getColumnsName());
        $columns = iterator_to_array($this->getTable()->getColumns());
        $columns = array_filter($columns, fn(Column $column): bool => $this->hasColumn($column));

        usort(
            $columns,
            fn(Column $column1, Column $column2): int => strcmp(
                $columnsPosition[$column1->getName()],
                $columnsPosition[$column2->getName()]
            )
        );

        return $columns;
    }

    /**
     * Has column?
     *
     * @param Column $column
     *
     * @return bool
     * @throws SchemaException
     */
    public function hasColumn(Column $column): bool
    {
        return
            $column->getTable() === $this->getTable() &&
            in_array($column->getName(), $this->getColumnsName());
    }
}
