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

use BcMath\Number;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;

/**
 * Exact decimal type.
 *
 * `DECIMAL`/`NUMERIC` columns are exact, arbitrary-precision numbers. Casting
 * them to a PHP `float` loses precision (e.g. monetary amounts), so this type
 * never goes through `float`: values are kept as their canonical numeric
 * string. When the entity property is typed as `\BcMath\Number` (PHP 8.4+),
 * the value is hydrated as a `Number` so exact decimal arithmetic is possible.
 */
class DecimalType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            $this->assertNullable($expected);

            return null;
        }

        $string = $this->assertNumericString($value);

        if (null !== $expected && false === $expected->isBuiltin()) {
            // If a property is typed `\BcMath\Number`, the class is necessarily
            // available (the user's code would not load otherwise), so hydrating
            // a Number here is always safe.
            if (Number::class === ltrim($expected->getName(), '\\')) {
                return new Number($string);
            }

            throw ValueException::castNotBuiltin($this);
        }

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->assertNumericString($value);
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $entityData, mixed $schemaData): bool
    {
        if (null === $entityData || null === $schemaData) {
            return $entityData === $schemaData;
        }

        // Compare the canonical numeric value so that equivalent representations
        // (e.g. "1.50" and "1.5", "+1" and "1") do not trigger a spurious UPDATE.
        return $this->normalize((string)$entityData) === $this->normalize((string)$schemaData);
    }

    /**
     * Assert that the value is a numeric scalar (or a Stringable numeric, e.g.
     * a `\BcMath\Number`) and return its string form, without ever casting to a
     * lossy `float`.
     *
     * @throws ValueException
     */
    private function assertNumericString(mixed $value): string
    {
        // A `\BcMath\Number` stringifies to its exact canonical value; if the
        // class is not available, `instanceof` simply yields false.
        if ($value instanceof Number) {
            return (string)$value;
        }

        if (false === is_scalar($value)) {
            throw ValueException::castError($this);
        }

        if (is_bool($value) || false === is_numeric($value)) {
            throw ValueException::castError($this);
        }

        return (string)$value;
    }

    /**
     * Normalize a numeric string to a canonical form for comparison only.
     *
     * Trims a leading "+", redundant leading zeros and trailing fractional
     * zeros, so equivalent decimal representations compare as equal without
     * going through a lossy `float`.
     */
    private function normalize(string $value): string
    {
        $value = ltrim(trim($value), '+');

        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }

        if (str_contains($value, '.')) {
            $value = rtrim($value, '0');
            $value = rtrim($value, '.');
        }

        $value = ltrim($value, '0');

        if ('' === $value || '.' === $value) {
            return '0';
        }

        if (str_starts_with($value, '.')) {
            $value = '0' . $value;
        }

        return ($negative ? '-' : '') . $value;
    }
}
