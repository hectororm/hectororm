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

use Hector\Query\Clause\Where;
use Hector\Query\Clause\Group;
use Hector\Query\Clause\Having;
use Hector\Query\Clause\Order;
use Hector\Query\Clause\Limit;
use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Orm\Query\Component\Conditions;
use Hector\Orm\Storage\EntityStorage;
use Hector\Query\StatementInterface;
use Hector\Schema\Exception\SchemaException;

abstract class Relationship
{
    use EntityAssert;
    use Where;
    use Group;
    use Having;
    use Order;
    use Limit;
    protected ReflectionEntity $sourceEntity;
    protected ReflectionEntity $targetEntity;
    protected array $sourceColumns;
    protected array $targetColumns;

    /**
     * OneToMany constructor.
     *
     * @param string $name
     * @param string $sourceEntity
     * @param string $targetEntity
     *
     * @throws OrmException
     */
    public function __construct(protected string $name, string $sourceEntity, string $targetEntity)
    {
        $this->sourceEntity = ReflectionEntity::get($sourceEntity);
        $this->targetEntity = ReflectionEntity::get($targetEntity);

        $this->resetWhere();
        $this->resetGroup();
        $this->resetHaving();
        $this->resetOrder();
        $this->resetLimit();
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get source entity.
     *
     * @return string
     */
    public function getSourceEntity(): string
    {
        return $this->sourceEntity->getName();
    }

    /**
     * Get target entity.
     *
     * @return string
     */
    public function getTargetEntity(): string
    {
        return $this->targetEntity->getName();
    }

    /**
     * Get source columns.
     *
     * @return string[]
     */
    public function getSourceColumns(): array
    {
        return $this->sourceColumns;
    }

    /**
     * Get target columns.
     *
     * @return string[]
     */
    public function getTargetColumns(): array
    {
        return $this->targetColumns;
    }

    /**
     * Get builder for given entities.
     *
     * @param Entity ...$entities
     *
     * @return Builder
     * @throws OrmException
     */
    abstract public function getBuilder(Entity ...$entities): Builder;

    /**
     * Add join to builder.
     *
     * @param Builder $builder
     * @param string $alias
     * @param string|null $initialAlias
     *
     * @return string Alias of join
     * @throws OrmException
     */
    abstract public function addJoinToBuilder(
        Builder $builder,
        string $alias,
        ?string $initialAlias = null,
    ): string;

    /**
     * Add condition to builder.
     *
     * @param Builder $builder
     * @param string $joinAlias
     * @param string $link
     * @param string $column
     * @param string|StatementInterface|callback|null ...$condition
     *
     * @return void
     * @throws OrmException
     */
    public function addConditionToBuilder(
        Builder|\Hector\Orm\Query\Statement\Conditions $builder,
        string $joinAlias,
        string $link,
        string $column,
        ...$condition
    ): void {
        try {
            $targetTable = $this->targetEntity->getTable();
            $builder->{match ($link) {
                Conditions::LINK_OR => 'orWhere',
                default => 'where',
            }}($targetTable->getColumn($column)->getName(true, $joinAlias), ...$condition);
        } catch (SchemaException $exception) {
            throw new OrmException(
                sprintf(
                    'Unable to resolve schema to add condition for relationship "%s" of entity "%s"',
                    $this->name,
                    $this->getSourceEntity()
                ),
                0,
                $exception
            );
        }
    }

    /**
     * Get result for given entities.
     *
     * @param Entity ...$entities
     *
     * @return Collection<Entity> Foreigners
     * @throws OrmException
     */
    abstract public function get(Entity ...$entities): Collection;

    /**
     * Link foreign entity to native entity.
     *
     * Call before entity saving.
     *
     * @param Entity $entity
     * @param Entity|Collection|null $foreign
     */
    public function linkForeign(Entity $entity, Entity|Collection|null $foreign): void
    {
    }

    /**
     * Link native entity to foreign entity.
     *
     * Call after entity saving.
     *
     * @param Entity $entity
     * @param Entity|Collection|null $foreign
     */
    public function linkNative(Entity $entity, Entity|Collection|null $foreign): void
    {
    }

    /**
     * Valid related entity or collection.
     *
     * @param mixed &$related
     *
     * @return bool
     */
    abstract public function valid(mixed &$related): bool;

    /**
     * Get reverse relationship.
     *
     * @param string $name
     *
     * @return Relationship
     * @throws OrmException
     */
    abstract public function reverse(string $name): Relationship;

    /**
     * Get entity values.
     *
     * @param ReflectionEntity $entityReflection
     * @param array $columns
     * @param Entity ...$entities
     *
     * @return array
     * @throws OrmException
     * @throws RelationException
     */
    protected function getEntityValues(ReflectionEntity $entityReflection, array $columns, Entity ...$entities): array
    {
        $entitiesValues = [];
        $nbColumns = count($columns);

        foreach ($entities as $entity) {
            $this->assertEntityType($entity, $entityReflection->getName());
            $entityValue = $entityReflection->getMapper()->collectEntity($entity, $columns);

            if (count($entityValue) !== $nbColumns) {
                throw RelationException::notAttemptedColumns($this->name, $columns, $entity::class);
            }

            if (empty(array_filter($entityValue, fn($value): bool => null !== $value))) {
                continue;
            }

            $entitiesValues[] = $entityValue;
        }

        return $entitiesValues;
    }

    /**
     * Has relation values?
     *
     * @param Entity $entity
     *
     * @return bool
     * @throws OrmException
     */
    protected function hasRelationValues(Entity $entity): bool
    {
        $values = Orm::get()->getMapper($entity)->collectEntity($entity, $this->getSourceColumns());
        $values = array_filter($values, fn($value): bool => null !== $value);

        return count($values) === count($this->getSourceColumns());
    }

    /**
     * Filter entities.
     *
     * @param Entity ...$entities
     *
     * @return array
     * @throws OrmException
     */
    protected function filterEntities(Entity ...$entities): array
    {
        return array_filter(
            $entities,
            function (Entity $entity) {
                $storageStatus = Orm::get()->getStatus($entity);

                if (null === $storageStatus) {
                    return $this->hasRelationValues($entity);
                }

                if ($storageStatus === EntityStorage::STATUS_TO_INSERT) {
                    return false;
                }

                return true;
            }
        );
    }

    /**
     * Tiny entities.
     *
     * @param array $columns
     * @param Entity ...$entities
     *
     * @return array
     * @throws OrmException
     */
    protected function tidyEntities(array $columns, Entity ...$entities): array
    {
        return array_map(
            function (Entity $entity) use ($columns) {
                $entityReflection = ReflectionEntity::get($entity::class);

                return [
                    'columns' => array_values($entityReflection->getMapper()->collectEntity($entity, $columns)),
                    'entity' => $entity,
                ];
            },
            $entities
        );
    }

    /**
     * New builder.
     *
     * @return Builder
     */
    protected function newBuilder(): Builder
    {
        $builder = $this->targetEntity->getName()::query();
        $builder->where = clone $this->where;
        $builder->group = clone $this->group;
        $builder->having = clone $this->having;
        $builder->order = clone $this->order;
        $builder->limit = clone $this->limit;

        return $builder;
    }
}
