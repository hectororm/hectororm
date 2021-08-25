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

namespace Hector\Orm\Tests\Fake\Type;

use Hector\Orm\DataType\AbstractType;
use Hector\Query\Statement\Raw;
use Hector\Query\Statement\SqlFunction;
use ReflectionNamedType;

class GeometryType extends AbstractType
{
    public const NAME = 'geometry';

    /**
     * @inheritDoc
     */
    public function fromSchemaFunction(): ?string
    {
        return 'ST_ASTEXT';
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ReflectionNamedType $declaredType = null): mixed
    {
        return new SqlFunction('ST_GeomFromText', new Raw('?', [$value]));
    }
}