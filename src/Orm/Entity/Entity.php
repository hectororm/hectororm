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

namespace Hector\Orm\Entity;

use Generator;
use Hector\Orm\Attributes;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Exception\NotFoundException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Mapper\GenericMapper;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;

#[Attributes\Collection(Collection::class)]
#[Attributes\Mapper(GenericMapper::class)]
abstract class Entity
{
    private EntityData $_hectorData;

    public function __serialize(): array
    {
        return [
            '_hectorData' => $this->_hectorData,
        ];
    }

    public function __unserialize(array $data): void
    {
        // Restore hector data
        if ($data['_hectorData']) {
            $this->_hectorData = $data['_hectorData'];
            $this->_hectorData->restore($this);
        }
    }

    public function __debugInfo(): ?array
    {
        return null;
    }

    /**
     * Get related.
     *
     * @return Related
     * @throws OrmException
     */
    final public function getRelated(): Related
    {
        return ReflectionEntity::get($this::class)->getHectorData($this)->getRelated();
    }

    /**
     * Get pivot.
     *
     * @return PivotData|null
     * @throws OrmException
     */
    final public function getPivot(): ?PivotData
    {
        return ReflectionEntity::get($this::class)->getHectorData($this)->getPivot();
    }

    /**
     * Compare entity with another one with primary values.
     *
     * @param Entity $entity
     *
     * @return bool
     * @throws OrmException
     */
    final public function isEqualTo(Entity $entity): bool
    {
        if (!($entity instanceof $this)) {
            return false;
        }

        // Same object?
        if ($this === $entity) {
            return true;
        }

        $mapper = Orm::get()->getMapper($this);
        $myPrimaries = array_filter($mapper->getPrimaryValue($this) ?? []);

        if (empty($myPrimaries)) {
            return false;
        }

        return $myPrimaries == $mapper->getPrimaryValue($entity);
    }

    /**
     * Is altered?
     *
     * @param string ...$column
     *
     * @return bool
     * @throws OrmException
     */
    public function isAltered(string ...$column): bool
    {
        return !empty(Orm::get()->getMapper($this)->getEntityAlteration($this, $column ?: null));
    }

    //////////////////////////////////
    /// ACTIONS ON ENTITY INSTANCE ///
    //////////////////////////////////

    /**
     * Save entity.
     *
     * @throws OrmException
     */
    public function save(): void
    {
        Orm::get()->save($this, true);
    }

    /**
     * Delete entity.
     *
     * @throws OrmException
     */
    public function delete(): void
    {
        Orm::get()->delete($this, true);
    }

    /**
     * Refresh entity.
     *
     * @throws OrmException
     */
    public function refresh(): void
    {
        Orm::get()->refresh($this);
    }

    /**
     * Edge loading of relations.
     *
     * @param array $relations
     *
     * @throws OrmException
     */
    public function load(array $relations): void
    {
        foreach ($relations as $key => $value) {
            $relationName = $value;
            if (is_array($value)) {
                $relationName = $key;
            }

            $related = $this->getRelated()->get($relationName);

            if (is_array($value)) {
                $related->load($value);
            }
        }
    }

    ///////////////
    /// BUILDER ///
    ///////////////

    /**
     * Query.
     *
     * @return Builder
     */
    public static function query(): Builder
    {
        return new Builder(static::class);
    }

    /**
     * Get entity at offset.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     *
     * @return static|null
     * @throws OrmException
     */
    public static function get(int $offset = 0): ?static
    {
        return static::query()->get($offset);
    }

    /**
     * Get entity at offset or fail if no result.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     *
     * @return static
     * @throws NotFoundException if no entity found
     * @throws OrmException
     */
    public static function getOrFail(int $offset = 0): static
    {
        return static::query()->getOrFail($offset);
    }

    /**
     * Get entity at offset or new entity if no result.
     *
     * If many results from query, only offset given in parameter is returned (default first).
     *
     * @param int $offset
     * @param array $initialData
     *
     * @return static
     * @throws OrmException
     */
    public static function getOrNew(int $offset = 0, array $initialData = []): static
    {
        return static::query()->getOrNew($offset, $initialData);
    }

    /**
     * Find one or more entities by primary.
     *
     * If one primary value is given, an entity
     * is returned, else a collection is returned.
     *
     * @param mixed $primaryValues
     *
     * @return static|Collection<static>|null
     * @throws OrmException
     */
    public static function find(mixed ...$primaryValues): static|Collection|null
    {
        return static::query()->find(...$primaryValues);
    }

    /**
     * Find all entities by primary.
     *
     * @param mixed $primaryValues
     *
     * @return Collection<static>
     * @throws OrmException
     */
    public static function findAll(mixed ...$primaryValues): Collection
    {
        return static::query()->findAll(...$primaryValues);
    }

    /**
     * Find entities by primary or fail.
     *
     * If one primary value is given, an entity
     * is returned, else a collection is returned.
     *
     * @param mixed ...$primaryValues
     *
     * @return static|Collection<static>
     * @throws OrmException
     */
    public static function findOrFail(mixed ...$primaryValues): static|Collection
    {
        return static::query()->findOrFail(...$primaryValues);
    }

    /**
     * Find entity by primary or new entity if no result.
     *
     * Only one primary value is accepted.
     *
     * @param mixed $primaryValue
     * @param array $initialData
     *
     * @return static|Collection<static>
     * @throws OrmException
     */
    public static function findOrNew(mixed $primaryValue, array $initialData = []): static|Collection
    {
        return static::query()->findOrNew($primaryValue, $initialData);
    }

    /**
     * Get all entities.
     *
     * @return Collection<static>
     * @throws OrmException
     */
    public static function all(): Collection
    {
        return static::query()->all();
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
    public static function chunk(int $limit, callable $callback): void
    {
        static::query()->chunk($limit, $callback);
    }

    /**
     * Iterate result with Generator.
     *
     * @return Generator<Entity>
     * @throws OrmException
     */
    public static function yield(): Generator
    {
        yield from static::query()->yield();
    }

    /**
     * Count.
     *
     * @return int
     * @throws OrmException
     */
    public static function count(): int
    {
        return static::query()->count();
    }
}