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

abstract class AbstractColumnOperation implements ColumnOperationInterface
{
    public function __construct(
        private string $table,
        private string $name,
        private string $type,
        private bool $nullable = false,
        private mixed $default = null,
        private bool $hasDefault = false,
        private bool $autoIncrement = false,
        private ?string $after = null,
        private bool $first = false,
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
     * Get column name.
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
     * Is nullable?
     *
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Get default value.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Has default value?
     *
     * @return bool
     */
    public function hasDefault(): bool
    {
        return $this->hasDefault || $this->nullable;
    }

    /**
     * Is auto increment?
     *
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * Get after column name.
     *
     * @return string|null
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * Is first?
     *
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->first;
    }
}
