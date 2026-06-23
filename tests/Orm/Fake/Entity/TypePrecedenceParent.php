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

namespace Hector\Orm\Tests\Fake\Entity;

use Hector\DataTypes\Type\NumericType;
use Hector\Orm\Attributes\Table;
use Hector\Orm\Attributes\Type;
use Hector\Orm\Entity\Entity;

#[Table('film')]
#[Type('release_year', NumericType::class)]
class TypePrecedenceParent extends Entity
{
    public ?int $film_id = null;
    public ?int $release_year = null;
}
