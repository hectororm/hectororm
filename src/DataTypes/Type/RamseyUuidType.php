<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\DataTypes\Type;

use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RamseyUuidType extends UuidType
{
    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if ($expected?->isBuiltin()) {
            throw ValueException::castError($this);
        }

        if (null !== $expected) {
            if (is_a($expected?->getName(), UuidInterface::class, true)) {
                throw ValueException::castError($this);
            }
        }

        return Uuid::fromString(parent::fromSchema($value));
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string
    {
        if (!$value instanceof UuidInterface) {
            throw ValueException::castError($this);
        }

        return parent::toSchema($value->toString());
    }
}
