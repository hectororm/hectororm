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
    private SplObjectStorage $entities;
    private SplObjectStorage $collections;

    public function __construct(private Orm $orm, private EntityStorage $storage, private Connection $connection)
    {
        $this->entities = new SplObjectStorage();
        $this->collections = new SplObjectStorage();
    }

    public function capture(Entity $entity): void
    {
        $reflection = ReflectionEntity::get($entity);
        if ($this->orm->getConnection($reflection->connection) !== $this->connection) {
            throw new OrmException('A relationship lifecycle operation cannot span multiple connections');
        }
        if ($this->entities->contains($entity)) {
            return;
        }

        $data = $reflection->getHectorData($entity)->__serialize();
        $data['pivot'] = null === $data['pivot'] ? null : clone $data['pivot'];
        $related = $entity->getRelated()->__serialize();
        $properties = [];
        if ($entity instanceof MagicEntity) {
            $property = $reflection->getProperty('_hectorAttributes', MagicEntity::class);
            $properties[] = [$property, true, $property->getValue($entity)];
        } else {
            foreach ($reflection->getTable()->getColumnsName() as $column) {
                if (false === $reflection->getClass()->hasProperty($column)) {
                    continue;
                }
                $property = $reflection->getProperty($column);
                $initialized = $property->isInitialized($entity);
                $properties[] = [$property, $initialized, $initialized ? $property->getValue($entity) : null];
            }
        }

        $this->entities[$entity] = [$this->orm->getStatus($entity), $data, $related, $properties];
        foreach ($related['related'] as $value) {
            if ($value instanceof Entity) {
                $this->capture($value);
            } elseif ($value instanceof Collection) {
                $this->captureCollection($value);
            }
        }
    }

    public function captureCollection(Collection $collection): void
    {
        if ($this->collections->contains($collection)) {
            return;
        }
        $this->collections[$collection] = $collection->lifecycleSnapshot();
        foreach ($collection as $entity) {
            if ($entity instanceof Entity) {
                $this->capture($entity);
            }
        }
        foreach ($collection->detached() as $entity) {
            $this->capture($entity);
        }
    }

    public function run(callable $operation): mixed
    {
        // Connection nesting uses a counter, not savepoints. Do not roll back a
        // caller-owned transaction when this individual operation fails.
        $nested = $this->connection->inTransaction();
        $savepoint = 'hector_lifecycle_' . spl_object_id($this);
        if ($nested) {
            $this->connection->execute('SAVEPOINT ' . $savepoint);
        } else {
            $this->connection->beginTransaction();
        }

        try {
            $result = $operation();
            if ($nested) {
                $this->connection->execute('RELEASE SAVEPOINT ' . $savepoint);
            } else {
                $this->connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            try {
                if ($nested) {
                    $this->connection->execute('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->execute('RELEASE SAVEPOINT ' . $savepoint);
                } else {
                    $this->connection->rollBack();
                }
            } finally {
                $this->restore();
            }
            throw $exception;
        }
    }

    private function restore(): void
    {
        foreach ($this->entities as $entity) {
            [$status, $data, $related, $properties] = $this->entities[$entity];
            foreach ($properties as [$property, $initialized, $value]) {
                if ($initialized) {
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
            $reflection = ReflectionEntity::get($entity);
            $reflection->getHectorData($entity)->__unserialize($data);
            $entity->getRelated()->__unserialize($related);
            if (null === $status) {
                $this->storage->detach($entity);
            } else {
                $this->storage->attach($entity, $status);
            }
        }
        foreach ($this->collections as $collection) {
            $collection->restoreLifecycleSnapshot($this->collections[$collection]);
        }
    }
}
