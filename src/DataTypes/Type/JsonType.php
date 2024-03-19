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
use stdClass;
use Throwable;

class JsonType extends AbstractType
{
    public function __construct(private bool $associative = true)
    {
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        $this->assertScalar($value);

        try {
            if (null !== $expected) {
                if (null === $value) {
                    if (false === $expected->allowsNull()) {
                        throw ValueException::castError($this);
                    }

                    return null;
                }

                if ($expected->isBuiltin()) {
                    if ($expected->getName() == 'array') {
                        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    }

                    if ($expected->getName() == 'string') {
                        return (string)$value;
                    }
                }

                if ($expected->getName() == stdClass::class) {
                    return json_decode($value, false, 512, JSON_FORCE_OBJECT);
                }

                throw ValueException::castError($this);
            }

            return json_decode($value, $this->associative, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw ValueException::castError($this, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string|bool|null
    {
        if (is_scalar($value)) {
            return (string)$value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw ValueException::castError($this, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $entityData, mixed $schemaData): bool
    {
        return $entityData == json_encode(json_decode($schemaData));
    }
}