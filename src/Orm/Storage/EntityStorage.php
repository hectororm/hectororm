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

namespace Hector\Orm\Storage;

use ArrayAccess;
use Countable;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\OrmException;
use IteratorAggregate;
use IteratorIterator;
use Traversable;
use UnexpectedValueException;
use WeakMap;

class EntityStorage implements Countable, IteratorAggregate, ArrayAccess
{
    public const STATUS_NONE = 0;
    public const STATUS_TO_INSERT = 1;
    public const STATUS_TO_UPDATE = 2;
    public const STATUS_TO_DELETE = 3;

    protected WeakMap $map;

    /**
     * EntityStorage constructor.
     */
    public function __construct()
    {
        $this->map = new WeakMap();
    }

    /**
     * PHP serialize method.
     *
     * @throws OrmException
     */
    public function __serialize(): array
    {
        throw new OrmException('Storage is not serializable');
    }

    /**
     * Attach entity or entities from a collection.
     *
     * @param Entity|Collection $entity
     * @param int $status
     */
    public function attach(Entity|Collection $entity, int $status = EntityStorage::STATUS_NONE): void
    {
        if ($entity instanceof Entity) {
            $this->map->offsetSet($entity, $status);

            return;
        }

        // Attach entities from collection
        foreach ($entity as $entityFromCollection) {
            $this->attach($entityFromCollection, $status);
        }
    }

    /**
     * Detach entity or entities from a collection.
     *
     * @param Entity|Collection $entity
     */
    public function detach(Entity|Collection $entity): void
    {
        if ($entity instanceof Entity) {
            $this->map->offsetunset($entity);

            return;
        }

        // Attach entities from collection
        foreach ($entity as $entityFromCollection) {
            $this->detach($entityFromCollection);
        }
    }

    /**
     * Contains entity?
     *
     * @param Entity|Collection $entity
     *
     * @return bool
     */
    public function contains(Entity|Collection $entity): bool
    {
        if ($entity instanceof Entity) {
            return $this->map->offsetExists($entity);
        }

        // Attach entities from collection
        foreach ($entity as $entityFromCollection) {
            if (!$this->map->offsetExists($entityFromCollection)) {
                return false;
            }
        }

        return true;
    }

    ///////////////////////////
    /// Countable Interface ///
    ///////////////////////////

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->map);
    }

    //////////////////////////
    /// Iterator Interface ///
    //////////////////////////

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new IteratorIterator($this->map);
    }

    /////////////////////////////
    /// ArrayAccess Interface ///
    /////////////////////////////

    /**
     * @inheritDoc
     */
    public function offsetExists($entity): bool
    {
        return $this->contains($entity);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($entity): int
    {
        if (false === $this->contains($entity)) {
            throw new UnexpectedValueException();
        }

        return $this->map->offsetGet($entity);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($entity, $status = EntityStorage::STATUS_NONE): void
    {
        $this->attach($entity, $status);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($entity): void
    {
        $this->detach($entity);
    }
}
