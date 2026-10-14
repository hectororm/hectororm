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
use Hector\Orm\Attributes\RelationshipAttribute;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Throwable;

class Relationships implements Countable
{
    use EntityAssert;

    private ReflectionEntity $entity;
    private array $list = [];
    /** @var array<string, RelationshipAttribute> */
    private array $declarations = [];
    /** @var array<string, int> Positions of this registry's active declarations in the shared path. */
    private array $resolving = [];
    /** @var list<string> Dependency path shared by synchronous resolution across mappers. */
    private static array $resolutionPath = [];

    /**
     * Relationships constructor.
     *
     * @param string $entity
     *
     * @throws OrmException
     */
    public function __construct(string $entity)
    {
        $this->entity = ReflectionEntity::get($entity);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->list + $this->declarations);
    }

    /**
     * Register an attribute without constructing its relationship.
     *
     * @internal
     */
    public function addDeclaration(RelationshipAttribute $attribute): void
    {
        $this->declarations[$attribute->name] = $attribute;
        unset($this->list[$attribute->name]);
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

        if (isset($this->resolving[$name])) {
            $path = array_slice(self::$resolutionPath, $this->resolving[$name]);
            $path[] = $this->entity->getName() . '.' . $name;

            throw RelationException::circularReference($path);
        }

        if (isset($this->list[$name])) {
            return $this->list[$name];
        }

        $this->resolving[$name] = count(self::$resolutionPath);
        self::$resolutionPath[] = $this->entity->getName() . '.' . $name;

        try {
            $relationship = $this->declarations[$name]->init($this);
            $this->list[$name] = $relationship;
            unset($this->declarations[$name]);

            return $relationship;
        } catch (Throwable $exception) {
            unset($this->list[$name]);

            throw $exception;
        } finally {
            array_pop(self::$resolutionPath);
            unset($this->resolving[$name]);
        }
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
            $this->assertExists($name);
            $candidate = $this->list[$name] ?? $this->declarations[$name];
            $target = $candidate instanceof Relationship ? $candidate->getTargetEntity() : $candidate->target;
            if ($target !== $foreignEntity) {
                throw RelationException::notFoundBetween($this->entity->getName(), $foreignEntity);
            }

            $relation = $this->get($name);

            if ($relation->getTargetEntity() === $foreignEntity) {
                return $relation;
            }

            throw RelationException::notFoundBetween($this->entity->getName(), $foreignEntity);
        }

        $relationshipFounds = [];

        foreach ($this->list + $this->declarations as $relationshipName => $candidate) {
            $target = $candidate instanceof Relationship ? $candidate->getTargetEntity() : $candidate->target;

            // Same relationship entity
            if ($target !== $foreignEntity) {
                continue;
            }

            // Inspect available column metadata before resolving a candidate. In particular,
            // an in-progress relationship with different columns is not a dependency of this lookup.
            if (null !== $columns) {
                $relationship = $candidate instanceof Relationship ? $candidate : $this->get((string)$relationshipName);
                if ($relationship->getSourceColumns() != $columns) {
                    continue;
                }
            }

            $relationshipFounds[] = (string)$relationshipName;
        }

        // If more than one result, it's ambiguous, and need to more descriptive
        if (count($relationshipFounds) > 1) {
            throw RelationException::ambiguous($this->entity->getName(), $foreignEntity);
        }

        // Return only result of relationship array
        if (count($relationshipFounds) === 1) {
            return $this->get(reset($relationshipFounds));
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
        return array_key_exists($name, $this->list) || array_key_exists($name, $this->declarations);
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
        if ($this->exists($name)) {
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
     * @param bool|null $orphanRemoval Null preserves historical orphan deletion until v2.
     *
     * @return OneToMany
     * @throws OrmException
     */
    public function hasMany(
        string $target,
        string $name,
        ?array $columns = null,
        ?bool $orphanRemoval = null,
    ): OneToMany
    {
        $this->assertEntity($target);

        return $this->list[$name] =
            new OneToMany(
                $name,
                $this->entity->getName(),
                $target,
                $columns,
                $orphanRemoval,
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
     * Declare a scalar relationship with an explicit or inferred parent role.
     */
    public function oneToOne(
        string $target,
        string $name,
        ?array $columns = null,
        ?bool $isParent = null,
        ?bool $orphanRemoval = null,
    ): OneToOne {
        $this->assertEntity($target);

        return $this->list[$name] = new OneToOne(
            $name,
            $this->entity->getName(),
            $target,
            $columns,
            $isParent,
            $orphanRemoval,
        );
    }

    /**
     * Declare a single child on the target side, without implicit orphan deletion.
     */
    public function hasOneChild(
        string $target,
        string $name,
        ?array $columns = null,
        bool $orphanRemoval = false,
    ): OneToOne {
        return $this->oneToOne($target, $name, $columns, isParent: true, orphanRemoval: $orphanRemoval);
    }

    /**
     * Structurally invert an explicitly named relationship on the target mapper.
     *
     * @throws OrmException
     */
    public function inverseOf(string $target, string $name, string $foreignName): Relationship
    {
        $targetReflection = ReflectionEntity::get($target);
        $source = $this->entity->getName();
        $target = $targetReflection->getName();
        $foreign = $targetReflection->getMapper()->getRelationships()->getWith($source, $foreignName);

        if ($foreign->getSourceEntity() !== $target) {
            throw new RelationException(sprintf(
                'Relationship "%s.%s" must originate from "%s" to be inverted as "%s.%s"',
                $target,
                $foreignName,
                $target,
                $source,
                $name,
            ));
        }

        $inverse = $foreign->reverse($name);
        if (
            $inverse === $foreign
            || $inverse->getName() !== $name
            || $inverse->getSourceEntity() !== $source
            || $inverse->getTargetEntity() !== $target
        ) {
            throw new RelationException(sprintf(
                'Relationship "%s.%s" returned an invalid inverse for "%s.%s"',
                $target,
                $foreignName,
                $source,
                $name,
            ));
        }

        return $this->list[$name] = $inverse;
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
        $targetReflection = ReflectionEntity::get($target);

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
