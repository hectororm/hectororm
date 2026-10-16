<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Storage;

use Closure;
use Hector\Connection\Connection;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use ReflectionProperty;
use SplObjectStorage;
use Throwable;

/**
 * Single-connection lifecycle unit of work. Snapshots preserve pending user edits
 * while undoing ORM-written keys, original data, statuses and removal tracking.
 *
 * @internal
 */
final class LifecycleTransaction
{
    /** @var SplObjectStorage<Entity, array> */
    private SplObjectStorage $entities;

    /** @var SplObjectStorage<LifecycleSnapshotInterface, array> */
    private SplObjectStorage $snapshots;

    /**
     * Create a transaction context for the given ORM storage and connection.
     */
    public function __construct(private Orm $orm, private EntityStorage $storage, private Connection $connection)
    {
        $this->entities = new SplObjectStorage();
        $this->snapshots = new SplObjectStorage();
    }

    /**
     * Capture an entity and its materialized relations once per transaction.
     */
    public function capture(Entity $entity): void
    {
        $reflection = ReflectionEntity::get($entity);
        if ($this->orm->getConnection($reflection->connection) !== $this->connection) {
            throw new OrmException('A relationship lifecycle operation cannot span multiple connections');
        }

        if (true === $this->entities->offsetExists($entity)) {
            return;
        }

        $this->captureSnapshot($reflection->getHectorData($entity));
        $related = $entity->getRelated();
        $this->captureSnapshot($related);
        $properties = $this->captureProperties($entity, $reflection);

        $this->entities[$entity] = [$this->orm->getStatus($entity), $properties];
        foreach ($related->getLifecycleReferences() as $value) {
            if ($value instanceof Entity) {
                $this->capture($value);
                continue;
            }

            if ($value instanceof Collection) {
                $this->captureCollection($value);
            }
        }
    }

    /**
     * Snapshot mapped values without invoking setters or converting their types.
     *
     * @return array<array{ReflectionProperty, bool, mixed}>
     */
    private function captureProperties(Entity $entity, ReflectionEntity $reflection): array
    {
        if ($entity instanceof MagicEntity) {
            $property = $reflection->getProperty('_hectorAttributes', MagicEntity::class);

            return [[$property, true, $property->getValue($entity)]];
        }

        $properties = [];
        foreach ($reflection->getTable()->getColumnsName() as $column) {
            if (false === $reflection->getClass()->hasProperty($column)) {
                continue;
            }

            $property = $reflection->getProperty($column);
            $initialized = $property->isInitialized($entity);
            $properties[] = [$property, $initialized, $initialized ? $property->getValue($entity) : null];
        }

        return $properties;
    }

    /**
     * Capture collection contents, explicit removals and the referenced entities.
     */
    public function captureCollection(Collection $collection): void
    {
        if (true === $this->snapshots->offsetExists($collection)) {
            return;
        }

        $this->captureSnapshot($collection);
        foreach ($collection as $entity) {
            if ($entity instanceof Entity) {
                $this->capture($entity);
            }
        }
        foreach ($collection->detached() as $entity) {
            $this->capture($entity);
        }
    }

    /**
     * Capture each participant once through its rollback-specific contract.
     */
    private function captureSnapshot(LifecycleSnapshotInterface $participant): void
    {
        if (false === $this->snapshots->offsetExists($participant)) {
            $this->snapshots[$participant] = $participant->lifecycleSnapshot();
        }
    }

    /**
     * Execute the operation with database and in-memory rollback on failure.
     */
    public function run(callable $operation): mixed
    {
        // A caller-owned transaction is preserved: the connection uses a savepoint.
        try {
            return $this->connection->transaction(fn(): mixed => $operation());
        } catch (Throwable $exception) {
            $this->restore();

            throw $exception;
        }
    }

    /**
     * Restore snapshots while preserving entity and collection object identities.
     */
    private function restore(): void
    {
        foreach ($this->entities as $entity) {
            [$status, $properties] = $this->entities[$entity];
            foreach ($properties as [$property, $initialized, $value]) {
                if (true === $initialized) {
                    if (false === $property->isInitialized($entity) || $property->getValue($entity) !== $value) {
                        $property->setValue($entity, $value);
                    }
                    continue;
                }
                $unset = Closure::bind(
                    static function (Entity $instance, string $name): void {
                        unset($instance->$name);
                    },
                    null,
                    $property->getDeclaringClass()->getName(),
                );
                $unset($entity, $property->getName());
            }
            if (null === $status) {
                $this->storage->detach($entity);
            } else {
                $this->storage->attach($entity, $status);
            }
        }
        foreach ($this->snapshots as $participant) {
            $participant->restoreLifecycleSnapshot($this->snapshots[$participant]);
        }
    }
}
