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
        return $entityData == $schemaData;
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
}
