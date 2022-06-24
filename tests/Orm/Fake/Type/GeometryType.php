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

use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\Type\AbstractType;
use Hector\Query\Statement\Raw;
use Hector\Query\Statement\SqlFunction;

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
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): SqlFunction
    {
        return new SqlFunction('ST_GeomFromText', new Raw(':position', ['position' => $value]));
    }
}