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
use Hector\Orm\DataType\TypeInterface;
use TypeError;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Type implements OrmAttribute
{
    /**
     * Type constructor.
     *
     * @param string $type
     * @param string $column
     *
     * @throws TypeError
     */
    public function __construct(
        public string $type,
        public string $column,
    ) {
        if (!is_a($this->type, TypeInterface::class, true)) {
            throw new TypeError(sprintf('First parameter must be an object of type "%s"', TypeInterface::class));
        }
    }
}