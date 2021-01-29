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
 * Interface TypeInterface.
 *
 * @package Hector\Orm\DataType
 */
interface TypeInterface
{
    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * From schema function.
     *
     * @return string|null
     */
    public function fromSchemaFunction(): ?string;

    /**
     * From schema to entity.
     *
     * @param mixed $value
     * @param ReflectionNamedType|null $declaredType
     *
     * @return mixed
     * @throws TypeException
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed;

    /**
     * From entity to schema.
     *
     * @param mixed $value
     * @param ReflectionNamedType|null $declaredType
     *
     * @return mixed
     * @throws TypeException
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed;

    /**
     * Get binding type.
     * Must return a PDO::PARAM_* value.
     *
     * @return int|null
     */
    public function getBindingType(): ?int;
}