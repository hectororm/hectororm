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

namespace Hector\Orm\DataType;

use Hector\Orm\Exception\TypeException;
use ReflectionNamedType;

/**
 * Class NumericType.
 *
 * @package Hector\Orm\DataType
 */
abstract class AbstractNumericType extends AbstractType implements TypeInterface
{
    protected const TYPE = null;

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (!is_scalar($value)) {
            throw TypeException::castError($this);
        }

        if (null !== $declaredType) {
            if ($declaredType->isBuiltin()) {
                settype($value, $declaredType->getName());

                return $value;
            }

            throw TypeException::castNotBuiltin($this);
        }

        settype($value, static::TYPE ?? 'int');

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (!is_scalar($value)) {
            throw TypeException::castError($this);
        }

        settype($value, static::TYPE ?? 'int');

        return $value;
    }
}