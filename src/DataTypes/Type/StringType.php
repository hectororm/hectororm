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

use BackedEnum;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use ReflectionEnum;
use Stringable;
use TypeError;
use ValueError;

class StringType extends AbstractType
{
    public function __construct(
        protected ?int $maxlength = null,
        protected ?string $encoding = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            $this->assertNullable($expected);

            return null;
        }

        $this->assertScalar($value);

        if (null !== $expected) {
            if ($expected->isBuiltin()) {
                settype($value, $expected->getName());

                return $value;
            }

            if (is_a($expected->getName(), BackedEnum::class, true)) {
                return $this->castToBackedEnum($expected->getName(), $value);
            }

            throw ValueException::castNotBuiltin($this);
        }

        return (string)$value;
    }

    /**
     * Hydrate a backed enum from a scalar schema value.
     *
     * PDO returns every column as a string, so an int-backed enum would receive a
     * numeric string and `Enum::from()` would raise a raw `TypeError`. The value is
     * coerced to the enum backing type first, and any failure (`TypeError` for a type
     * mismatch, `ValueError` for an unknown case) is wrapped in a `ValueException`.
     *
     * @param class-string<BackedEnum> $enum
     *
     * @throws ValueException
     */
    private function castToBackedEnum(string $enum, mixed $value): BackedEnum
    {
        $backingType = (string)(new ReflectionEnum($enum))->getBackingType();

        if ('int' === $backingType && is_numeric($value)) {
            $value = (int)$value;
        }

        try {
            return $enum::from($value);
        } catch (TypeError | ValueError $exception) {
            throw ValueException::castError($this, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?string
    {
        if (null === $value) {
            return null;
        }

        // A backed enum is the counterpart of the hydration done in fromSchema():
        // persist its backing value so the round-trip is symmetric.
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (!is_scalar($value)) {
            if ($value instanceof Stringable) {
                return (string)$value;
            }

            throw ValueException::castError($this);
        }

        if (null !== $this->maxlength) {
            return mb_substr(
                (string)$value,
                0,
                $this->maxlength,
                $this->encoding,
            );
        }

        return (string)$value;
    }
}
