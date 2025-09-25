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
use Hector\Orm\Query\Component\Conditions;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Row;
use Hector\Query\Statement\SqlFunction;
use Hector\Schema\Column;

/**
 * Query builder for a specific entity.
 *
 * @template T of Entity
 * @extends QueryBuilder<T>
 */
class Builder extends QueryBuilder
{
    public const FROM_ALIAS = 'main';

    /** @var ReflectionEntity<T> Reflection of the target entity. */
    private ReflectionEntity $entityReflection;
    /** @var array<string,mixed> Relations to eager‑load. */
    public array $with = [];

    /**
     * EntityQuery constructor.
     *
     * @param class-string<T> $entity Fully qualified entity class name.
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
     * @inheritDoc
     */
    public function resetWhere(): static
    {
        $this->where = new Conditions($this, $this->entityReflection);

        return $this;
    }

    /**
     * Reset entity columns (select list) to the default set.
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
     * Define eager‑loaded relations.
     *
     * @param array<string,mixed> $with List of relations to load.
     *
     * @return static
     */
    public function with(array $with): static
    {
        $this->with = $with;

        return $this;
    }

    /**
     * Add a pivot column to the SELECT list.
     *
     * @param string $column Column name in the pivot table.
     * @param string $alias Alias to use for the column.
     *
     * @return static
     * @internal
     *
     */
    public function withPivotColumn(string $column, string $alias): static
    {
        $this->column($column, $alias);

        return $this;
    }

    /**
     * Retrieve a single entity at the given offset.
     *
     * @param int $offset Zero‑based offset (default 0).
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
     * Retrieve a single entity at the given offset or throw if none found.
     *
     * @param int $offset Zero‑based offset (default 0).
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
     * Retrieve a single entity at the given offset or create a new one.
     *
     * @param int $offset Zero‑based offset (default 0).
     * @param array $initialData Optional data to hydrate the new entity.
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
     * Build the WHERE clause for a primary‑key lookup.
     *
     * @param mixed ...$primaryValues Primary key values (single or composite).
     *
     * @return void
     * @throws MapperException If the entity has no primary key or the supplied
     *                         values do not match the key definition.
     */
    private function findQuery(mixed ...$primaryValues): void
    {
        $table = $this->entityReflection->getTable();
        $primaryIndex = $this->entityReflection->getPrimaryIndex();
        if (null === $primaryIndex) {
            throw new MapperException(sprintf('No primary key on "%s" table', $table->getFullName()));
        }

        $primaryColumns = $primaryIndex->getColumnsName(true, static::FROM_ALIAS);
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
     * Find one or many entities by primary key.
     *
     * @param mixed ...$primaryValues Primary key values.
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
     * Find a collection of entities by primary key(s).
     *
     * @param mixed ...$primaryValues Primary key values.
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
     * Find one or many entities by primary key, throwing if none found.
     *
     * @param mixed ...$primaryValues Primary key values.
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
     * Find an entity by primary key or create a new one if none exists.
     *
     * @param mixed $primaryValue Primary key value.
     * @param array $initialData Optional data for the new entity.
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
     * Retrieve all matching entities.
     *
     * @return Collection<T>
     * @throws OrmException
     */
    public function all(): Collection
    {
        return $this->entityReflection->getMapper()->fetchAllWithBuilder($this);
    }

    /**
     * Iterate over the result set lazily.
     *
     * @return LazyCollection<T>
     * @throws OrmException
     */
    public function yield(): LazyCollection
    {
        return $this->entityReflection->getMapper()->yieldWithBuilder($this);
    }

    /**
     * Process results in chunks.
     *
     * @param int $limit Number of rows per chunk.
     * @param callable $callback Function to call for each chunk (receives a Collection<T>).
     * @param bool $lazy If true, use the lazy generator; otherwise fetch each chunk eagerly.
     *
     * @return void
     * @throws OrmException
     */
    public function chunk(int $limit, callable $callback, bool $lazy = true): void
    {
        if (true === $lazy) {
            foreach ($this->yield()->chunk($limit)->map($callback) as $item) {
            }
            return;
        }

        $offset = 0;
        do {
            // Get collection
            $this->limit($limit, $offset);
            $collection = $this->all();

            if (false === $collection->isEmpty()) {
                $callback($collection);
                $offset += $limit;
            }
        } while (false === $collection->isEmpty());
    }
}