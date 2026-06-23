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

class NumericType extends AbstractType
{
    public function __construct(
        protected string $numericType = 'int'
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
                $this->assertNumeric($value, $expected->getName());
                settype($value, $expected->getName());

                return $value;
            }

            throw ValueException::castNotBuiltin($this);
        }

        $this->assertNumeric($value, $this->numericType);
        settype($value, $this->numericType);

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            return null;
        }

        $this->assertScalar($value);
        $this->assertNumeric($value, $this->numericType);

        settype($value, $this->numericType);

        return $value;
    }

    /**
     * Reject non-numeric strings before an `int`/`float` cast.
     *
     * `settype()` would otherwise silently coerce a non-numeric string such as
     * "abc" to `0`/`0.0`, hiding the error. Booleans and already-numeric values
     * keep their unambiguous cast (e.g. the legitimate `float` to `int`
     * truncation driven by the configured numeric type).
     *
     * @throws ValueException
     */
    private function assertNumeric(mixed $value, string $targetType): void
    {
        if ('int' !== $targetType && 'float' !== $targetType) {
            return;
        }

        if (is_string($value) && false === is_numeric($value)) {
            throw ValueException::castError($this);
        }
    }
}
