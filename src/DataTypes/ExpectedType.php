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

namespace Hector\DataTypes;

use ReflectionNamedType;

class ExpectedType
{
    /**
     * From.
     *
     * @param ReflectionNamedType|string $type
     * @param bool $allowsNull
     *
     * @return static
     */
    public static function from(ReflectionNamedType|string $type, bool $allowsNull = false): static
    {
        if ($type instanceof ReflectionNamedType) {
            return static::fromReflection($type);
        }

        return static::fromString($type, $allowsNull);
    }

    /**
     * From reflection.
     *
     * @param ReflectionNamedType $type
     *
     * @return static
     */
    public static function fromReflection(ReflectionNamedType $type): static
    {
        return new static($type->getName(), $type->allowsNull(), $type->isBuiltin());
    }

    /**
     * From string.
     *
     * @param string $name
     * @param bool $allowsNull
     *
     * @return static
     */
    public static function fromString(string $name, bool $allowsNull = false): static
    {
        return match ($name) {
            'bool', 'boolean' => new static('bool', $allowsNull, true),
            'int', 'integer' => new static('int', $allowsNull, true),
            'float' => new static('float', $allowsNull, true),
            'string' => new static('string', $allowsNull, true),
            'array' => new static('array', $allowsNull, true),
            default => new static($name, $allowsNull, false),
        };
    }

    public function __construct(
        protected string $name,
        protected bool $allowsNull = false,
        protected bool $builtin = false,
    ) {
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Allows null?
     *
     * @return bool
     */
    public function allowsNull(): bool
    {
        return $this->allowsNull;
    }

    /**
     * Is builtin?
     *
     * @return bool
     */
    public function isBuiltin(): bool
    {
        return $this->builtin;
    }
}