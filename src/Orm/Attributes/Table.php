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
use Hector\Connection\Connection;

#[Attribute(Attribute::TARGET_CLASS)]
class Table implements OrmAttribute
{
    public function __construct(
        public ?string $table = null,
        public ?string $schema = null,
        public string $connection = Connection::DEFAULT_NAME
    ) {
    }
}