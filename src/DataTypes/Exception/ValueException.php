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

namespace Hector\DataTypes\Exception;

use Hector\DataTypes\Type\TypeInterface;
use Throwable;
use ValueError;

class ValueException extends ValueError
{
    /**
     * Cast error.
     *
     * @param TypeInterface $type
     * @param Throwable|null $previous
     *
     * @return static
     */
    public static function castError(TypeInterface $type, ?Throwable $previous = null): static
    {
        return new static(sprintf('Unable to cast "%s" type', $type::class), 0, $previous);
    }

    /**
     * Cast to not builtin type.
     *
     * @param TypeInterface $type
     * @param Throwable|null $previous
     *
     * @return static
     */
    public static function castNotBuiltin(TypeInterface $type, ?Throwable $previous = null): static
    {
        return new static(sprintf('Unable to cast "%s" type to not builtin type', $type::class), 0, $previous);
    }
}
