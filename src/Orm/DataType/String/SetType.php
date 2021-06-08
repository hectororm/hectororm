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

namespace Hector\Orm\DataType\String;

use Hector\Orm\DataType\AbstractType;
use Hector\Orm\DataType\TypeInterface;
use Hector\Orm\Exception\TypeException;
use ReflectionNamedType;

/**
 * Class SetType.
 */
class SetType extends AbstractType implements TypeInterface
{
    public const NAME = 'set';

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (!is_string($value)) {
            throw TypeException::castError($this);
        }

        // Explode string
        $value = explode(',', $value);

        if (null !== $declaredType) {
            if ($declaredType->isBuiltin()) {
                settype($value, $declaredType->getName());

                return $value;
            }

            throw TypeException::castNotBuiltin($this);
        }

        return (array)$value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (!is_array($value)) {
            throw TypeException::castError($this);
        }

        return implode(',', $value);
    }
}