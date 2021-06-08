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

use DateTime;
use DateTimeInterface;
use Exception;
use Hector\Orm\Exception\TypeException;
use ReflectionNamedType;
use Throwable;

/**
 * Class AbstractDateType.
 */
abstract class AbstractDateType extends AbstractType implements TypeInterface
{
    public const NAME = null;
    protected const FORMAT = 'Y-m-d H:i:s';

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        try {
            if (null === $declaredType) {
                return new DateTime($value);
            }

            if ($declaredType->getName() == 'string') {
                return (string)$value;
            }

            if ($declaredType->getName() == 'int') {
                return (int)strtotime((string)$value);
            }

            if (is_a($declaredType->getName(), DateTimeInterface::class, true)) {
                $class = $declaredType->getName();

                return new $class((string)$value);
            }
        } catch (Throwable $e) {
            throw TypeException::castError($this, $e);
        }

        throw TypeException::castError($this);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        if (is_string($value)) {
            $value = new DateTime($value);
        }

        if (is_numeric($value)) {
            try {
                $value = new DateTime(sprintf('@%d', $value));
            } catch (Throwable) {
                throw TypeException::castError($this);
            }
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(static::FORMAT);
        }

        throw TypeException::castError($this);
    }
}