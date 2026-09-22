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

namespace Hector\Orm\Relationship;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;

class OneToMany extends RegularRelationship
{
    use ValidToManyTrait;

    /**
     * OneToMany constructor.
     *
     * @param string $name
     * @param string $sourceEntity
     * @param string $targetEntity
     * @param array|null $columns
     * @param bool|null $orphanRemoval Null preserves historical orphan deletion until v2.
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        ?array $columns = null,
        private ?bool $orphanRemoval = null,
    ) {
        parent::__construct($name, $sourceEntity, $targetEntity, $columns);

        // Deduct columns
        if (empty($columns)) {
            if (empty($columns = $this->sourceEntity->getPrimaryIndex()?->getColumnsName())) {
                throw new RelationException(sprintf(
                    'Unable to deduct columns of entity "%s" for relation "%s"',
                    $sourceEntity,
                    $name,
                ));
            }

            $this->sourceColumns = $columns;
            $this->targetColumns = $columns;
        }
    }

    /**
     * @inheritDoc
     */
    public function setOrphanRemoval(?bool $orphanRemoval): void
    {
        $this->orphanRemoval = $orphanRemoval;
    }

    /**
     * Null denotes the pre-v2 compatibility default (delete detached children).
     *
     * @return bool|null
     */
    public function getOrphanRemoval(): ?bool
    {
        return $this->orphanRemoval;
    }

    /**
     * @inheritDoc
     */
    public function hasLifecyclePolicy(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function prepareLifecycle(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (!$foreign instanceof Collection) {
            return;
        }

        foreach ($foreign->detached() as $child) {
            if (!$child instanceof ($this->getTargetEntity())) {
                throw new RelationException('Invalid detached child entity type');
            }

            Orm::get()->lifecycle()->cancelPendingInsert($child);
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareAssignment(
        Entity|Collection|null $previous,
        Entity|Collection|null $value,
    ): Entity|Collection|null {
        // Preserve legacy whole-collection assignment until callers opt in.
        if (null === $this->orphanRemoval) {
            return $value;
        }

        $value ??= new Collection();
        if (!$value instanceof Collection) {
            throw new RelationException('Foreign must be a collection');
        }

        if ($previous instanceof Collection && $previous !== $value) {
            // Only known, visible members are replaced. Never query unseen rows
            // to infer removals from a possibly filtered or limited collection.
            foreach ($previous as $child) {
                if (false === $value->contains($child)) {
                    $value->trackDetached($child);
                }
            }
            foreach ($previous->detached() as $child) {
                $value->trackDetached($child);
            }
        }

        return $value;
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function linkNative(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (!$foreign instanceof Collection) {
            throw new RelationException('Foreign must be a collection');
        }

        $lifecycle = Orm::get()->lifecycle();
        $lifecycle->transaction($entity, function () use ($entity, $foreign, $lifecycle): void {
            $lifecycle->track($foreign);
            foreach ($foreign as $child) {
                if (!$child instanceof ($this->getTargetEntity())) {
                    throw new RelationException('Invalid child entity type');
                }
            }
            foreach ($foreign->detached() as $child) {
                if (!$child instanceof ($this->getTargetEntity())) {
                    throw new RelationException('Invalid detached child entity type');
                }
                $lifecycle->removeChild($this, $entity, $child, $this->orphanRemoval ?? true);
            }

            $this->linkChildren($entity, $foreign);
            $foreign->clearDetached();
        });
    }

    /**
     * Propagate parent keys to retained and newly attached children.
     *
     * @param Entity $entity
     * @param Collection<Entity> $foreign
     * @throws OrmException
     */
    private function linkChildren(Entity $entity, Collection $foreign): void
    {
        $entityReflection = ReflectionEntity::get($entity::class);

        $sourceColumns = $entityReflection->getMapper()->collectEntity($entity, $this->getSourceColumns());
        $targetColumns = array_combine($this->getTargetColumns(), $sourceColumns);

        /** @var Entity $foreignEntity */
        foreach ($foreign as $foreignEntity) {
            $foreignEntityReflection = ReflectionEntity::get($foreignEntity::class);
            $targetColumnsOrigin = $foreignEntityReflection->getMapper()->collectEntity(
                $foreignEntity,
                $this->getTargetColumns()
            );

            // Already hydrated?
            if ($targetColumns == array_filter($targetColumnsOrigin, fn($value): bool => null !== $value)) {
                // Not altered?
                if (false === $foreignEntity->isAltered(...$this->getTargetColumns())) {
                    continue;
                }
            }

            // Hydrate foreign entity
            $foreignEntity->getRelated()->invalidateParent($this);
            $foreignEntityReflection->getMapper()->hydrateEntity($foreignEntity, $targetColumns);

            // Save foreign
            $foreignEntity->save();
            if (EntityStorage::STATUS_NONE !== Orm::get()->getStatus($foreignEntity)) {
                throw new RelationException('Child saving was prevented; the lifecycle operation was rolled back');
            }

            if (
                false === self::keysMatch(
                    $targetColumns,
                    $foreignEntityReflection->getMapper()->collectEntity($foreignEntity, $this->getTargetColumns()),
                )
            ) {
                throw new RelationException('Child linking was prevented; the lifecycle operation was rolled back');
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        return new ManyToOne(
            $name,
            $this->getTargetEntity(),
            $this->getSourceEntity(),
            array_combine($this->getTargetColumns(), $this->getSourceColumns())
        );
    }

    /**
     * @inheritDoc
     */
    protected function switchIntoEntities(Collection $foreigners, Entity ...$entities): void
    {
        // Tidy entities
        $entities = $this->tidyEntities($this->sourceColumns, ...$entities);
        $foreigners = $this->tidyEntities($this->targetColumns, ...$foreigners);

        // Get inverted relationship
        try {
            $relationship = $this->targetEntity->getMapper()->getRelationships()->getWith(
                foreignEntity: $this->sourceEntity->class,
                columns: $this->getTargetColumns()
            );
        } catch (RelationException) {
            $relationship = null;
        }

        foreach ($entities as $entity) {
            $foreignersFiltered = array_filter(
                $foreigners,
                fn($foreign): bool => self::keysMatch($foreign['columns'], $entity['columns'])
            );
            $foreignersFiltered = array_column($foreignersFiltered, 'entity');

            $entity['entity']->getRelated()->setLoaded(
                $this->getName(),
                new Collection($foreignersFiltered)
            );

            if (null !== $relationship) {
                /** @var Entity $foreignEntity */
                foreach ($foreignersFiltered as $foreignEntity) {
                    $foreignEntity->getRelated()->setLoaded($relationship->getName(), $entity['entity']);
                }
            }
        }
    }
}
