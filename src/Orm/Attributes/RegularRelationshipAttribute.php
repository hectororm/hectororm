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

abstract class RegularRelationshipAttribute extends RelationshipAttribute
{
    public function __construct(
        string $target,
        string $name,
        public ?array $columns = null,
        ...$params
    ) {
        parent::__construct($target, $name, $params);
    }
}
