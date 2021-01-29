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

use DateTime;
use Hector\Orm\Attributes as Orm;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Tests\Fake\Mapper\CityMapper;

#[Orm\Mapper(CityMapper::class)]
#[Orm\HasOne(Country::class, 'country')]
class City extends Entity
{
    public ?int $city_id;
    public ?string $city;
    public ?int $country_id;
    public ?DateTime $last_update;
}