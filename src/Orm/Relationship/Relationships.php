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

use Countable;
use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;

/**
 * Class Relationships.
 */
class Relationships implements Countable
{
    use EntityAssert;

    private ReflectionEntity $entity;
    private array $list = [];

    /**
     * Relationships constructor.
     *
     * @param string $entity
     *
     * @throws OrmException
     */
    public function __construct(string $entity)
    {
        $this->entity = new ReflectionEntity($entity);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->list);
    }

    /**
     * Get relationship.
     *
     * @param string $name
     *
     * @return Relationship
     * @throws RelationException
     */
    public function get(string $name): Relationship
    {
        $this->assertExists($name);

        return $this->list[$name];
    }

    /**
     * Get relationship with another entity.
     *
     * @param string $foreignEntity
     * @param string|null $name
     * @param array|null $columns
     *
     * @return Relationship
     * @throws RelationException
     */
    public function getWith(string $foreignEntity, ?string $name = null, ?array $columns = null): Relationship
    {
        // Given name so... more simple to found :)
        if (null !== $name) {
            $relation = $this->get($name);

            if (null !== $relation && $relation->getTargetEntity() === $foreignEntity) {
                return $relation;
            }

            throw RelationException::notFoundBetween($this->entity->getName(), $foreignEntity);
        }

        $relationshipFounds = [];

        /** @var Relationship $relationship */
        foreach ($this->list as $relationship) {
            // Same relationship entity
            if ($relationship->getTargetEntity() !== $foreignEntity) {
                continue;
            }

            // Search columns if given
            if (null !== $columns && $relationship->getSourceColumns() != $columns) {
                continue;
            }

            $relationshipFounds[] = $relationship;
        }

        // If more than one result, it's ambiguous, and need to more descriptive
        if (count($relationshipFounds) > 1) {
            throw RelationException::ambiguous($this->entity->getName(), $foreignEntity);
        }

        // Return only result of relationship array
        if (count($relationshipFounds) === 1) {
            return reset($relationshipFounds);
        }

        throw RelationException::notFoundBetween($this->entity->getName(), $foreignEntity);
    }

    /**
     * Exists?
     *
     * @param string $name
     *
     * @return bool
     */
    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->list);
    }

    /**
     * Assert exists.
     *
     * @param string $name
     *
     * @throws RelationException
     */
    public function assertExists(string $name): void
    {
        if (array_key_exists($name, $this->list)) {
            return;
        }

        throw RelationException::notFound($name, $this->entity->getName());
    }

    /**
     * Has many.
     *
     * @param string $target
     * @param string $name
     * @param array|null $columns
     *
     * @return OneToMany
     * @throws OrmException
     */
    public function hasMany(string $target, string $name, ?array $columns = null): OneToMany
    {
        $this->assertEntity($target);

        return $this->list[$name] =
            new OneToMany(
                $name,
                $this->entity->getName(),
                $target,
                $columns
            );
    }

    /**
     * Has one.
     *
     * @param string $target
     * @param string $name
     * @param array|null $columns
     *
     * @return ManyToOne
     * @throws OrmException
     */
    public function hasOne(string $target, string $name, ?array $columns = null): ManyToOne
    {
        $this->assertEntity($target);

        return $this->list[$name] =
            new ManyToOne(
                $name,
                $this->entity->getName(),
                $target,
                $columns
            );
    }

    /**
     * Belongs to.
     *
     * @param string $target
     * @param string $name
     * @param string|null $foreignName
     *
     * @return Relationship
     * @throws OrmException
     */
    public function belongsTo(string $target, string $name, ?string $foreignName = null): Relationship
    {
        $targetReflection = new ReflectionEntity($target);

        /** @var Entity $target */
        $foreignRelationships = $targetReflection->getMapper()->getRelationships();
        $foreignRelationship = $foreignRelationships->getWith($this->entity->getName(), $foreignName);

        return $this->list[$name] = $foreignRelationship->reverse($name);
    }

    /**
     * Belongs to many.
     *
     * @param string $target
     * @param string $name
     * @param string|null $pivotTable
     * @param array|null $columnsFrom
     * @param array|null $columnsTo
     *
     * @return ManyToMany
     * @throws OrmException
     */
    public function belongsToMany(
        string $target,
        string $name,
        ?string $pivotTable = null,
        ?array $columnsFrom = null,
        ?array $columnsTo = null
    ): ManyToMany {
        $this->assertEntity($target);

        return $this->list[$name] =
            new ManyToMany(
                $name,
                $this->entity->getName(),
                $target,
                $pivotTable,
                $columnsFrom,
                $columnsTo,
            );
    }
}