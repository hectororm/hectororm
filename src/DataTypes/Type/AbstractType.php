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

namespace Hector\DataTypes\Type;

use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use ValueError;

abstract class AbstractType implements TypeInterface
{
    /**
     * @inheritDoc
     */
    public function fromSchemaFunction(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function toSchemaFunction(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $entityData, mixed $schemaData): bool
    {
        // null is only equal to null: loose `==` treated null as equal to '' or 0.
        if (null === $entityData || null === $schemaData) {
            return $entityData === $schemaData;
        }

        // Compare scalars by their string form: this keeps the legitimate
        // int/float vs numeric-string juggling between the entity (e.g. int 1)
        // and the schema value coming from the database (e.g. string "1") while
        // still detecting real changes that loose `==` hid (e.g. "1e3" vs "1000",
        // "1.0" vs "1").
        if (is_scalar($entityData) && is_scalar($schemaData)) {
            return (string)$entityData === (string)$schemaData;
        }

        return $entityData === $schemaData;
    }

    /**
     * @inheritDoc
     */
    public function getBindingType(): ?int
    {
        return null;
    }

    /**
     * Assert scalar value.
     *
     * @param mixed $value
     */
    protected function assertScalar(mixed $value): void
    {
        if (!is_scalar($value)) {
            throw new ValueError(sprintf('Value must be scalar, "%s" type given', get_debug_type($value)));
        }
    }

    /**
     * Assert that a `null` value is allowed by the expected type.
     *
     * When no expected type is provided (e.g. an untyped/magic property), `null`
     * is considered permissible. Otherwise it is allowed only when the expected
     * type is nullable.
     *
     * @param ExpectedType|null $expected
     *
     * @throws ValueException when `null` is not allowed
     */
    protected function assertNullable(?ExpectedType $expected): void
    {
        if (null === $expected) {
            return;
        }

        if (false === $expected->allowsNull()) {
            throw ValueException::castError($this);
        }
    }
}
