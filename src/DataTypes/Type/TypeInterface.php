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

use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\TypeException;

interface TypeInterface
{
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
     * @param ExpectedType|null $expected
     *
     * @return mixed
     * @throws TypeException
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed;

    /**
     * To schema function.
     *
     * @return string|null
     */
    public function toSchemaFunction(): ?string;

    /**
     * From entity to schema.
     *
     * @param mixed $value
     * @param ExpectedType|null $expected
     *
     * @return mixed
     * @throws TypeException
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): mixed;

    /**
     * Get binding type.
     * Must return a PDO::PARAM_* value.
     *
     * @return int|null
     */
    public function getBindingType(): ?int;
}