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
 * Class Column.
 */
class Column
{
    use NameHelperTrait;

    public function __construct(
        private string $name,
        private int $position,
        private mixed $default,
        private bool $nullable,
        private string $type,
        private bool $auto_increment = false,
        private ?int $maxlength = null,
        private ?int $numeric_precision = null,
        private ?int $numeric_scale = null,
        private bool $unsigned = false,
        private ?string $charset = null,
        private ?string $collation = null,
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
            'position' => $this->position,
            'default' => $this->default,
            'nullable' => $this->nullable,
            'type' => $this->type,
            'auto_increment' => $this->auto_increment,
            'maxlength' => $this->maxlength,
            'numeric_precision' => $this->numeric_precision,
            'numeric_scale' => $this->numeric_scale,
            'unsigned' => $this->unsigned,
            'charset' => $this->charset,
            'collation' => $this->collation,
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
        $this->position = $data['position'];
        $this->default = $data['default'];
        $this->nullable = $data['nullable'];
        $this->type = $data['type'];
        $this->auto_increment = $data['auto_increment'];
        $this->maxlength = $data['maxlength'];
        $this->numeric_precision = $data['numeric_precision'];
        $this->numeric_scale = $data['numeric_scale'];
        $this->unsigned = $data['unsigned'];
        $this->charset = $data['charset'];
        $this->collation = $data['collation'];
        $this->table = null;
    }

    /**
     * Get name.
     *
     * @param bool $quoted
     * @param string|null $tableAlias
     *
     * @return string
     */
    public function getName(bool $quoted = false, ?string $tableAlias = null): string
    {
        $name = $this->name;

        $quoted && $name = $this->quoteName($name);
        null !== $tableAlias && $name = $this->addAliasToName($name, $tableAlias);

        return $name;
    }

    /**
     * Get full name.
     *
     * @param bool $quoted
     *
     * @return string
     * @throws SchemaException
     */
    public function getFullName(bool $quoted = false): string
    {
        return sprintf('%s.%s', $this->getTable()->getFullName($quoted), $this->getName($quoted));
    }

    /**
     * Get position.
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Has default?
     *
     * @return bool
     */
    public function hasDefault(): bool
    {
        return $this->isNullable() || null !== $this->default;
    }

    /**
     * Get default.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Is nullable?
     *
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
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
     * Is auto increment?
     *
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return $this->auto_increment;
    }

    /**
     * Get max length.
     *
     * @return int|null
     */
    public function getMaxlength(): ?int
    {
        return $this->maxlength;
    }

    /**
     * Get numeric precision.
     *
     * @return int|null
     */
    public function getNumericPrecision(): ?int
    {
        return $this->numeric_precision;
    }

    /**
     * Get numeric scale.
     *
     * @return int|null
     */
    public function getNumericScale(): ?int
    {
        return $this->numeric_scale;
    }

    /**
     * Is unsigned?
     *
     * @return bool
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * Get charset.
     *
     * @return string|null
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * Get collation.
     *
     * @return string|null
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }

    /**
     * Get table.
     *
     * @return Table
     * @throws SchemaException
     */
    public function getTable(): Table
    {
        return $this->table ?? throw new SchemaException('No table attached to the column');
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
     * Is primary?
     *
     * @return bool
     * @throws SchemaException
     */
    public function isPrimary(): bool
    {
        $primaryIndex = $this->getTable()->getPrimaryIndex();

        if (null === $primaryIndex) {
            return false;
        }

        return $primaryIndex->hasColumn($this);
    }
}