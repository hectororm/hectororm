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
use Hector\Orm\Attributes\BelongsTo;
use Hector\Orm\Attributes\HasOne;
use Hector\Orm\Entity\Entity;

#[HasOne(City::class, 'city')]
#[BelongsTo(Customer::class, 'customers')]
class Address extends Entity
{
    public ?int $address_id = null;
    public ?string $address = null;
    public ?string $address2 = null;
    public ?string $district = null;
    public ?int $city_id = null;
    public ?string $postal_code = null;
    public ?string $phone = null;
    public ?string $location = null;
    public ?DateTime $last_update = null;

    public function getCity(): City
    {
        return $this->getRelated()->get('city');
    }
}