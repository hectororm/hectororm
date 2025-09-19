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

use Hector\Orm\Entity\MagicEntity;

/**
 *
 * @property int $payment_id
 * @property int $customer_id
 * @property int $staff_id
 * @property int $rental_id
 * @property float $amount
 * @property string $payment_date
 */
class Payment extends MagicEntity
{
}