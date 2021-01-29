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
use stdClass;
use Throwable;

/**
 * Class JsonType.
 *
 * @package Hector\Orm\DataType
 */
class JsonType extends AbstractType implements TypeInterface
{
    public const NAME = 'json';

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (!is_scalar($value)) {
            throw TypeException::castError($this);
        }

        try {
            if (null !== $declaredType) {
                if ($declaredType->isBuiltin()) {
                    if ($declaredType->getName() == 'array') {
                        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    }

                    if ($declaredType->getName() == 'string') {
                        return (string)$value;
                    }
                }

                if ($declaredType->getName() == stdClass::class) {
                    return json_decode($value, false, 512, JSON_FORCE_OBJECT);
                }

                throw TypeException::castError($this);
            }

            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw TypeException::castError($this, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (is_scalar($value)) {
            return (string)$value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw TypeException::castError($this, $e);
        }
    }
}