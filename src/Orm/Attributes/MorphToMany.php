<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Attributes;

use Attribute;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class MorphToMany extends RelationshipAttribute implements OrmAttribute
{
    public function __construct(
        string $target,
        string $name,
        public string $pivotTable,
        public array $columnsFrom,
        public array $columnsTo,
        public array $discriminator,
        ...$params
    ) {
        parent::__construct($target, $name, $params);
    }

    /**
     * @inheritDoc
     */
    protected function addRelationship(Relationships $relationships): Relationship
    {
        return $relationships->morphToMany(
            $this->target,
            $this->name,
            $this->pivotTable,
            $this->columnsFrom,
            $this->columnsTo,
            $this->discriminator,
        );
    }
}
