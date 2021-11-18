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

class ManyToOne extends RegularRelationship
{
    /**
     * ManyToOne constructor.
     *
     * @param string $name
     * @param string $sourceEntity
     * @param string $targetEntity
     * @param array|null $columns
     *
     * @throws OrmException
     */
    public function __construct(string $name, string $sourceEntity, string $targetEntity, ?array $columns = null)
    {
        parent::__construct($name, $sourceEntity, $targetEntity, $columns);

        // Deduct columns
        if (empty($columns)) {
            $columns = $this->targetEntity->getTable()->getPrimaryIndex()->getColumnsName();
            $this->sourceColumns = $columns;
            $this->targetColumns = $columns;
        }
    }

    /**
     * @inheritDoc
     */
    public function valid(Entity|Collection|null $related): bool
    {
        if (null === $related) {
            return true;
        }

        if (!$related instanceof Entity) {
            return false;
        }

        return ($this->getTargetEntity() === get_class($related));
    }

    /**
     * @inheritDoc
     */
    protected function switchIntoEntities(Collection $foreigners, Entity ...$entities): void
    {
        // Tidy entities
        $entities = $this->tidyEntities($this->sourceColumns, ...$entities);
        $foreigners = $this->tidyEntities($this->targetColumns, ...$foreigners);

        foreach ($entities as $entity) {
            $foreignersFiltered = array_filter($foreigners, fn($foreign) => $foreign['columns'] == $entity['columns']);
            $foreignersFiltered = array_column($foreignersFiltered, 'entity');

            $entity['entity']->getRelated()->set($this->getName(), reset($foreignersFiltered) ?: null);
        }
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function linkForeign(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (null !== $foreign && !$foreign instanceof Entity) {
            throw new RelationException('Foreign must be an entity');
        }

        $entityReflection = ReflectionEntity::get($entity::class);
        $foreignReflection = ReflectionEntity::get($foreign::class);

        if (null === $foreign) {
            $entityReflection->getMapper()->hydrateEntity($entity, array_fill_keys($this->getSourceColumns(), null));
            return;
        }

        // Save foreign entity to get collect columns ; ONLY IF ALTERED!
        if ($foreign->isAltered(...$this->getTargetColumns())) {
            $foreign->save();
        }

        $targetColumns = $foreignReflection->getMapper()->collectEntity($foreign, $this->getTargetColumns());
        $sourceColumns = array_combine($this->getSourceColumns(), $targetColumns);

        $entityReflection->getMapper()->hydrateEntity($entity, $sourceColumns);
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        return new OneToMany(
            $name,
            $this->getTargetEntity(),
            $this->getSourceEntity(),
            array_combine($this->getTargetColumns(), $this->getSourceColumns())
        );
    }
}