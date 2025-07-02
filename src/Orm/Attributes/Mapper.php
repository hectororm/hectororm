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

namespace Hector\Orm\Attributes;

use Attribute;
use Hector\Orm\Mapper\Mapper as MapperInterface;
use TypeError;

#[Attribute(Attribute::TARGET_CLASS)]
class Mapper implements OrmAttribute
{
    public function __construct(public string $mapper)
    {
        if (!is_a($mapper, MapperInterface::class, true)) {
            throw new TypeError(sprintf('Excepted %s class, got %s', MapperInterface::class, $mapper));
        }
    }
}