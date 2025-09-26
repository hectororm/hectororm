<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2023 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Primary implements OrmAttribute
{
    public array $columns;

    public function __construct(
        string ...$column,
    ) {
        $this->columns = $column;
    }
}
