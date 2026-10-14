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

namespace Hector\Schema;

use Hector\Schema\Exception\SchemaException;

class CheckConstraint
{
    public function __construct(
        private ?string $name,
        private string $expression,
        private ?bool $enforced = null,
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
            'expression' => $this->expression,
            'enforced' => $this->enforced,
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
        $this->expression = $data['expression'];
        $this->enforced = $data['enforced'];
        $this->table = null;
    }

    /**
     * Get the declared name, or null for an unnamed constraint.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the SQL expression verbatim, without the enclosing CHECK (...).
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the constraint's enforcement state, or null when it is unknown.
     *
     * Does not describe session settings that temporarily disable enforcement.
     *
     * @return bool|null
     */
    public function isEnforced(): ?bool
    {
        return $this->enforced;
    }

    /**
     * Get table.
     *
     * @return Table
     * @throws SchemaException
     */
    public function getTable(): Table
    {
        return $this->table ?? throw new SchemaException('No table attached to the check constraint');
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
}
