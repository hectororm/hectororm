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
use Stringable;

class StringType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        $this->assertScalar($value);

        if (null !== $expected) {
            if ($expected->isBuiltin()) {
                settype($value, $expected->getName());

                return $value;
            }

            if (is_a($expected->getName(), BackedEnum::class)) {
                return $expected->getName()::from($value);
            }

            throw ValueException::castNotBuiltin($this);
        }

        return (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string
    {
        if (!is_scalar($value)) {
            if ($value instanceof Stringable) {
                return (string)$value;
            }

            throw ValueException::castError($this);
        }

        return (string)$value;
    }
}