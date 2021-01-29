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

use Hector\Orm\Entity\Entity;

class Country extends Entity
{
    public ?int $country_id;
    public ?string $country;
    public ?string $last_update;
}