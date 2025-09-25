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

namespace Hector\Orm\Collection;

use Closure;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;

/**
 * Collection of {@see \Hector\Orm\Entity\Entity} objects.
 *
 * This class extends the generic {@see \Hector\Collection\Collection} and adds
 * ORM‑specific behaviours (save, delete, refresh, relation loading, …).
 *
 * @template T of \Hector\Orm\Entity\Entity
 * @extends \Hector\Collection\Collection<T>
 */
class Collection extends \Hector\Collection\Collection
{
    private array $detached = [];

    /**
     * @inheritDoc
     * @return LazyCollection<T> The lazy collection instance.
     */
    protected function newLazy(iterable|Closure $iterable): LazyCollection
    {
        return new LazyCollection($iterable);
    }

    /**
     * Save entities.
     *
     * @throws OrmException
     */
    public function save(bool $cascade = false): void
    {
        /** @var Entity $entity */
        foreach ($this as $entity) {
            $entity->save($cascade);
        }
    }

    /**
     * Delete entities.
     *
     * @throws OrmException
     */
    public function delete(): void
    {
        $keys = [];

        /** @var Entity $entity */
        foreach ($this as $key => $entity) {
            $keys[] = $key;

            if (null === Orm::get()->getStatus($entity)) {
                continue;
            }

            $entity->delete();
        }

        foreach ($keys as $key) {
            unset($this[$key]);
        }
    }

    /**
     * Refresh entities.
     *
     * @throws OrmException
     */
    public function refresh(): void
    {
        /** @var Entity $entity */
        foreach ($this as $entity) {
            if (null === Orm::get()->getStatus($entity)) {
                continue;
            }

            $entity->refresh();
        }
    }

    /**
     * Edge loading of relations.
     *
     * @param array $relations
     *
     * @return static
     * @throws OrmException
     */
    public function load(array $relations): static
    {
        /** @var Entity $entity */
        $entity = $this->filterInstanceOf(Entity::class)->first();

        if (null === $entity) {
            return $this;
        }

        $entityReflection = ReflectionEntity::get($entity);

        foreach ($relations as $key => $value) {
            $relationName = $value;
            if (is_array($value)) {
                $relationName = $key;
            }

            $related = $entityReflection->getMapper()->getRelationships()->get($relationName)->get(...$this);

            if (is_array($value)) {
                $related->load($value);
            }
        }

        return $this;
    }

    /**
     * Is in?
     *
     * @param mixed $value
     * @param bool $strict
     *
     * @return bool
     * @throws OrmException
     */
    public function contains(mixed $value, bool $strict = false): bool
    {
        if (!$value instanceof Entity) {
            return parent::contains($value, $strict);
        }

        foreach ($this as $entity) {
            if ($entity->isEqualTo($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->detached[] = $this->offsetGet($offset);
        }

        parent::offsetUnset($offset);
    }

    /**
     * Get detached entities.
     *
     * @return iterable
     * @internal
     */
    public function detached(): iterable
    {
        yield from $this->detached;
    }

    /**
     * Clear collection.
     *
     * @internal
     */
    public function clearDetached(): void
    {
        $this->detached = [];
    }
}