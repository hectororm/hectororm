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
use TypeError;
use ValueError;

class EnumType extends AbstractType
{
    public function __construct(protected string $enum, protected bool $try = false)
    {
        if (false === is_a($this->enum, BackedEnum::class, true)) {
            throw new ValueError('Enum must be a PHP 8.1 backed enum type');
        }
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
                if (!in_array($expected->getName(), ['int', 'string'], true)) {
                    throw ValueException::castError($this);
                }

                $value = $this->resolveEnum($value)?->value;

                if (null !== $value) {
                    settype($value, $expected->getName());
                }

                return $value;
            }

            if ($this->enum !== $expected->getName()) {
                throw ValueException::castError($this);
            }
        }

        return $this->resolveEnum($value);
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            return null;
        }

        if (false === is_a($value, $this->enum)) {
            if (is_scalar($value)) {
                return $this->resolveEnum($value)?->value;
            }

            throw ValueException::castError($this);
        }

        return $value?->value;
    }

    /**
     * Resolve a scalar to the configured backed enum.
     *
     * PDO returns every column as a string, so an int-backed enum would receive a
     * numeric string and `Enum::from()` would raise a raw `TypeError`. The value is
     * coerced to the enum backing type first. In strict mode any failure
     * (`TypeError`/`ValueError`) is wrapped in a `ValueException`; in "try" mode an
     * unknown value yields `null`.
     *
     * @throws ValueException
     */
    private function resolveEnum(mixed $value): ?BackedEnum
    {
        if ('int' === (string)(new ReflectionEnum($this->enum))->getBackingType() && is_numeric($value)) {
            $value = (int)$value;
        }

        if (true === $this->try) {
            // A type mismatch (e.g. a non-numeric string for an int-backed enum)
            // is treated as "no matching case" in try mode rather than an error.
            try {
                return $this->enum::tryFrom($value);
            } catch (TypeError) {
                return null;
            }
        }

        try {
            return $this->enum::from($value);
        } catch (TypeError | ValueError $exception) {
            throw ValueException::castError($this, $exception);
        }
    }
}
