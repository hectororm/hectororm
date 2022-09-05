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

namespace Hector\Orm\Query;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Collection\LazyCollection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\NotFoundException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Row;
use Hector\Query\Statement\SqlFunction;
use Hector\Schema\Column;

/**
 * @template T
 */
class Builder extends QueryBuilder
{
    public const FROM_ALIAS = 'main';

    private ReflectionEntity $entityReflection;
    public array $with = [];

    /**
     * EntityQuery constructor.
     *
     * @param class-string<T> $entity
     *
     * @throws OrmException
     */
    public function __construct(string $entity)
    {
        $this->entityReflection = ReflectionEntity::get($entity);
        parent::__construct(Orm::get()->getConnection($this->entityReflection->connection));
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function reset(): static
    {
        parent::reset();
        $this->resetEntityColumns();

        return $this;
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function resetFrom(): static
    {
        parent::resetFrom();

        $table = $this->entityReflection->getTable();
        $this->from($table->getFullName(true), static::FROM_ALIAS);

        return $this;
    }

    /**
     * Reset entity columns.
     *
     * @return static
     * @throws OrmException
     */
    public function resetEntityColumns(): static
    {
        $this->resetColumns();
        $table = $this->entityReflection->getTable();

        // Entity columns
        /** @var Column $column */
        foreach ($table->getColumns() as $column) {
            $type = $this->entityReflection->getType($column->getName());

            if (null !== ($sqlFunction = $type->fromSchemaFunction())) {
                $this->column(
                    new SqlFunction($sqlFunction, $column->getName(true, static::FROM_ALIAS)),
                    $column->getName(true)
                );
                continue;
            }

            $this->column($column->getName(true, static::FROM_ALIAS));
        }

        return $this;
    }

    /**
     * With.
     *
     * @param array $with
     *
     * @return static
     */
    public function with(array $with): static
    {
        $this->with = $with;

        return $this;
    }

    /**
     * With pivot column.
     *
     * @param string $column
     * @param string $alias
     *
     * @return static
     * @internal
     */
    public function withPivotColumn(string $column, string $alias): static
    {
        $this->column($column, $alias);

        return $this;
    }

    /**
     * Get entity at offset.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     *
     * @return T|null
     * @throws OrmException
     */
    public function get(int $offset = 0): ?Entity
    {
        $this->limit(1, $offset);

        return $this->entityReflection->getMapper()->fetchOneWithBuilder($this);
    }

    /**
     * Get entity at offset or fail if no result.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     *
     * @return T
     * @throws NotFoundException if no entity found
     * @throws OrmException
     */
    public function getOrFail(int $offset = 0): Entity
    {
        $entity = $this->get($offset);

        if (null === $entity) {
            throw new NotFoundException();
        }

        return $entity;
    }

    /**
     * Get entity at offset or new entity if no result.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     * @param array $initialData
     *
     * @return T
     * @throws OrmException
     */
    public function getOrNew(int $offset = 0, array $initialData = []): Entity
    {
        $entity = $this->get($offset);

        if (null === $entity) {
            $entity = $this->entityReflection->newInstance();
            $this->entityReflection->getMapper()->hydrateEntity($entity, $initialData);
        }

        return $entity;
    }

    /**
     * Find.
     *
     * If one primary value is given, an entity
     * is returned, else a collection is returned.
     *
     * @param mixed ...$primaryValues
     *
     * @throws OrmException
     */
    private function findQuery(mixed ...$primaryValues): void
    {
        $table = $this->entityReflection->getTable();

        $primaryIndex = $table->getPrimaryIndex();
        if (null === $primaryIndex) {
            throw new MapperException(sprintf('No primary key on "%s" table', $table->getFullName()));
        }

        $primaryColumns = $table->getPrimaryIndex()->getColumnsName(true, static::FROM_ALIAS);
        $nbColumns = count($primaryColumns);

        foreach ($primaryValues as &$primaryValue) {
            $primaryValue = (array)$primaryValue;

            if ($nbColumns !== ($nbCurrent = count($primaryValue))) {
                throw new MapperException(
                    sprintf(
                        'Primary key for "%s" entity contains %d column(s), %d given',
                        $this->entityReflection->class,
                        $nbColumns,
                        $nbCurrent
                    )
                );
            }
        }

        $this->whereIn(new Row(...$primaryColumns), $primaryValues);
    }

    /**
     * Find.
     *
     * If one primary value is given, an entity
     * is returned, else a collection is returned.
     *
     * @param mixed ...$primaryValues
     *
     * @return T|Collection<T>|null
     * @throws OrmException
     */
    public function find(mixed ...$primaryValues): Entity|Collection|null
    {
        $this->findQuery(...$primaryValues);

        if (count($primaryValues) === 1) {
            return $this->get();
        }

        return $this->all();
    }

    /**
     * Find all.
     *
     * @param mixed ...$primaryValues
     *
     * @return Collection<T>
     * @throws OrmException
     */
    public function findAll(mixed ...$primaryValues): Collection
    {
        $this->findQuery(...$primaryValues);

        return $this->all();
    }

    /**
     * Find or fail.
     *
     * If one primary value is given, an entity
     * is returned, else a collection is returned.
     *
     * @param mixed ...$primaryValues
     *
     * @return T|Collection<T>
     * @throws OrmException
     */
    public function findOrFail(mixed ...$primaryValues): Entity|Collection
    {
        $result = $this->find(...$primaryValues);

        if (empty($result)) {
            throw new NotFoundException();
        }

        return $result;
    }

    /**
     * Find entity or new entity if no result.
     *
     * Only one primary value is accepted.
     *
     * @param mixed $primaryValue
     * @param array $initialData
     *
     * @return T
     * @throws OrmException
     */
    public function findOrNew(mixed $primaryValue, array $initialData = []): Entity
    {
        $entity = $this->find($primaryValue);

        if (null === $entity) {
            $entity = $this->entityReflection->newInstance();
            $this->entityReflection->getMapper()->hydrateEntity($entity, $initialData);
        }

        return $entity;
    }

    /**
     * All.
     *
     * @return Collection<T>
     * @throws OrmException
     */
    public function all(): Collection
    {
        return $this->entityReflection->getMapper()->fetchAllWithBuilder($this);
    }

    /**
     * Iterate result with Generator.
     *
     * @return LazyCollection<T>
     * @throws OrmException
     */
    public function yield(): LazyCollection
    {
        return $this->entityReflection->getMapper()->yieldWithBuilder($this);
    }

    /**
     * Chunk results.
     *
     * @param int $limit
     * @param callable $callback
     *
     * @return void
     * @throws OrmException
     */
    public function chunk(int $limit, callable $callback): void
    {
        foreach ($this->yield()->chunk($limit)->map($callback) as $item) {
        }
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function andWhere(...$condition): static
    {
        if (isset($condition[0]) && $this->isConditionOnRelationship($condition[0])) {
            $conditionColumns = explode('.', $condition[0]);
            $depth = count($conditionColumns) - 1;

            if (!$this->entityReflection->getMapper()->getRelationships()->exists($conditionColumns[0])) {
                return parent::andWhere(...$condition);
            }

            $i = 0;
            $entityClass = $this->entityReflection->class;
            $alias = static::FROM_ALIAS;
            do {
                $mapper = Orm::get()->getMapper($entityClass);
                $relationship = $mapper->getRelationships()->get($conditionColumns[$i]);
                $alias = $relationship->addJoinToBuilder($this, $alias);
                $entityClass = $relationship->getTargetEntity();
                $i++;
            } while ($i < $depth);

            $relationship->addConditionToBuilder($this, $alias, end($conditionColumns), ...array_slice($condition, 1));

            $this->distinct(true);

            return $this;
        }

        return parent::andWhere(...$condition);
    }

    /**
     * Is condition on relationship?
     *
     * @param $condition
     *
     * @return bool
     */
    private function isConditionOnRelationship($condition): bool
    {
        if (!is_string($condition)) {
            return false;
        }

        return preg_match('/^(\w+\.)+[\w`]+$/i', $condition) === 1;
    }
}