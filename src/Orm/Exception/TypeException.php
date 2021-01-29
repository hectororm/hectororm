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

namespace Hector\Orm\Exception;

use Hector\Orm\DataType\TypeInterface;
use Hector\Schema\Column;
use Hector\Schema\Exception\SchemaException;
use Throwable;

/**
 * Class TypeException.
 *
 * @package Hector\Orm\Exception
 */
class TypeException extends OrmException
{
    /**
     * Not nullable.
     *
     * @param Column $column
     *
     * @return TypeException
     * @throws SchemaException
     */
    public static function notNullable(Column $column): TypeException
    {
        return new static(sprintf('Column "%s" is not nullable', $column->getFullName()));
    }

    /**
     * Unknown.
     *
     * @param string $type
     *
     * @return static
     */
    public static function unknown(string $type): static
    {
        return new static(sprintf('Type "%s" not declared in Hector', $type));
    }

    /**
     * Bad type.
     *
     * @param object $type
     *
     * @return static
     */
    public static function badType(object $type): static
    {
        return new static(
            sprintf(
                'Type "%s" must implement "%s" interface',
                get_class($type),
                TypeInterface::class
            )
        );
    }

    /**
     * Cast error.
     *
     * @param TypeInterface $type
     * @param Throwable|null $previous
     *
     * @return static
     */
    public static function castError(TypeInterface $type, Throwable $previous = null): static
    {
        return new static(sprintf('Unable to cast "%s" type', $type->getName()), 0, $previous);
    }

    /**
     * Cast to not builtin type.
     *
     * @param TypeInterface $type
     * @param Throwable|null $previous
     *
     * @return static
     */
    public static function castNotBuiltin(TypeInterface $type, Throwable $previous = null): static
    {
        return new static(sprintf('Unable to cast "%s" type to not builtin type', $type->getName()), 0, $previous);
    }
}