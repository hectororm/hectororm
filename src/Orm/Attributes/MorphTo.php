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

/**
 * Repeat the attribute with the same name to let one relationship resolve to several target entities.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class MorphTo extends RelationshipAttribute implements OrmAttribute
{
    /** @var list<MorphTo> Other targets declared under the same name. */
    private array $siblings = [];

    public function __construct(
        string $target,
        string $name,
        public array $columns,
        public array $discriminator,
    ) {
        parent::__construct($target, $name);
    }

    /**
     * Add a target declared under the same name.
     *
     * @internal
     */
    public function addSibling(MorphTo $attribute): void
    {
        $this->siblings[] = $attribute;
    }

    /**
     * Get the target entities declared under this name.
     *
     * @return list<string>
     * @internal
     */
    public function getTargets(): array
    {
        return array_map(static fn(MorphTo $attribute): string => $attribute->target, [$this, ...$this->siblings]);
    }

    /**
     * @inheritDoc
     */
    protected function addRelationship(Relationships $relationships): Relationship
    {
        $relationship = $relationships->morphTo($this->target, $this->name, $this->columns, $this->discriminator);

        foreach ($this->siblings as $sibling) {
            $relationship->addType($sibling->target, $sibling->columns, $sibling->discriminator);
        }

        return $relationship;
    }
}
