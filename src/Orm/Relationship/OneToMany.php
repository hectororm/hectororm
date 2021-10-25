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

class OneToMany extends RegularRelationship
{
    use ValidToManyTrait;

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
            $columns = $this->sourceEntity->getTable()->getPrimaryIndex()->getColumnsName();
            $this->sourceColumns = $columns;
            $this->targetColumns = $columns;
        }
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

        $entityReflection = new ReflectionEntity($entity::class);

        $sourceColumns = $entityReflection->getMapper()->collectEntity($entity, $this->getSourceColumns());
        $targetColumns = array_combine($this->getTargetColumns(), $sourceColumns);

        /** @var Entity $foreignEntity */
        foreach ($foreign as $foreignEntity) {
            $foreignEntityReflection = new ReflectionEntity($foreignEntity::class);
            $foreignEntityReflection->getMapper()->hydrateEntity($foreignEntity, $targetColumns);
        }

        // Save foreign
        $foreign->save();
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
                columns:       $this->getTargetColumns()
            );
        } catch (RelationException) {
            $relationship = null;
        }

        foreach ($entities as $entity) {
            $foreignersFiltered = array_filter($foreigners, fn($foreign) => $foreign['columns'] == $entity['columns']);
            $foreignersFiltered = array_column($foreignersFiltered, 'entity');

            $entity['entity']->getRelated()->set(
                $this->getName(),
                $this->targetEntity->newInstanceOfCollection($foreignersFiltered)
            );

            if (null !== $relationship) {
                /** @var Entity $foreignEntity */
                foreach ($foreignersFiltered as $foreignEntity) {
                    $foreignEntity->getRelated()->set($relationship->getName(), $entity['entity']);
                }
            }
        }
    }
}