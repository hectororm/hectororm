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

namespace Hector\Orm;

use Hector\Connection\Connection;
use Hector\Connection\ConnectionSet;
use Hector\Connection\Exception\NotFoundException;
use Hector\Orm\DataType\DataTypeSet;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Event;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Mapper\Mapper;
use Hector\Orm\Query\Builder;
use Hector\Orm\Storage\EntityStorage;
use Hector\Query\QueryBuilder;
use Hector\Schema\SchemaContainer;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Class Orm.
 */
class Orm
{
    public static ?Orm $instance = null;
    public static int $alias = 0;
    protected ConnectionSet $connections;
    protected DataTypeSet $dataTypes;
    protected SchemaContainer $schemaContainer;
    protected EventDispatcherInterface $eventDispatcher;
    protected EntityStorage $storage;
    private array $reflections = [];
    private array $mappers = [];

    /**
     * Orm constructor.
     *
     * @param Connection|ConnectionSet $connection
     * @param SchemaContainer $schemaContainer
     * @param EventDispatcherInterface|null $eventDispatcher
     *
     * @throws OrmException
     */
    public function __construct(
        Connection|ConnectionSet $connection,
        SchemaContainer $schemaContainer,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        if ($connection instanceof Connection) {
            $connection = new ConnectionSet($connection);
        }
        $this->connections = $connection;
        $this->dataTypes = new DataTypeSet();
        $this->dataTypes->initDefaults();
        $this->schemaContainer = $schemaContainer;
        $this->eventDispatcher = $eventDispatcher ?? new Event\NullEventDispatcher();
        $this->storage = new EntityStorage();

        if (null !== self::$instance) {
            throw new OrmException('ORM already initialized');
        }
        self::$instance = $this;
    }

    /**
     * Get Orm instance.
     *
     * @return Orm
     * @throws OrmException
     */
    public static function get(): Orm
    {
        return self::$instance ?? throw new OrmException('ORM not initialized');
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'connections' => $this->connections,
            'dataTypes' => $this->dataTypes,
            'schemaContainer' => $this->schemaContainer,
            'reflections' => $this->reflections,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     *
     * @throws OrmException
     */
    public function __unserialize(array $data): void
    {
        $this->connections = $data['connections'];
        $this->dataTypes = $data['dataTypes'];
        $this->schemaContainer = $data['schemaContainer'];
        $this->reflections = $data['reflections'];

        $this->eventDispatcher = new Event\NullEventDispatcher();
        $this->storage = new EntityStorage();

        if (null !== self::$instance) {
            throw new OrmException('ORM already initialized');
        }
        static::$instance = $this;
    }

    /**
     * Get event dispatcher.
     *
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    /**
     * Set event dispatcher.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Get connection.
     *
     * @param string $connectionName
     *
     * @return Connection
     * @throws OrmException
     */
    public function getConnection(string $connectionName = Connection::DEFAULT_NAME): Connection
    {
        try {
            return $this->connections->getConnection($connectionName);
        } catch (NotFoundException) {
            throw new OrmException(sprintf('Connection named "%s" does not exists', $connectionName));
        }
    }

    /**
     * Get connections.
     *
     * @return ConnectionSet
     */
    public function getConnections(): ConnectionSet
    {
        return $this->connections;
    }

    /**
     * Get schema container.
     *
     * @return SchemaContainer
     */
    public function getSchemaContainer(): SchemaContainer
    {
        return $this->schemaContainer;
    }

    /**
     * Get query builder.
     *
     * @param string $connectionName
     *
     * @return QueryBuilder
     * @throws OrmException
     */
    public function getQueryBuilder(string $connectionName = Connection::DEFAULT_NAME): QueryBuilder
    {
        return new QueryBuilder($this->getConnection($connectionName));
    }

    /**
     * Get entity reflection.
     *
     * @param Entity|string $entity
     *
     * @return ReflectionEntity
     * @throws OrmException
     * @internal
     */
    public function getEntityReflection(Entity|string $entity): ReflectionEntity
    {
        if ($entity instanceof Entity) {
            $entity = $entity::class;
        }

        if (array_key_exists($entity, $this->reflections)) {
            return $this->reflections[$entity];
        }

        return $this->reflections[$entity] = new ReflectionEntity($entity);
    }

    /**
     * Get mapper of entity.
     *
     * @param Entity|string $entity
     *
     * @return Mapper
     * @throws OrmException
     * @internal
     */
    public function getMapper(Entity|string $entity): Mapper
    {
        if (!is_string($entity)) {
            $entity = $entity::class;
        }

        return
            $this->mappers[$entity] ??
            $this->mappers[$entity] = $this->getEntityReflection($entity)->newInstanceOfMapper($this->storage);
    }

    /**
     * Get data types.
     *
     * @return DataTypeSet
     * @internal
     */
    public function getDataTypes(): DataTypeSet
    {
        return $this->dataTypes;
    }

    /**
     * Get builder for entity.
     *
     * @param string $entity
     *
     * @return Builder
     * @throws OrmException
     */
    public function getBuilder(string $entity): Builder
    {
        if (!is_a($entity, Entity::class, true)) {
            throw new InvalidArgumentException(sprintf('Argument must be a valid entity class name "%s"', $entity));
        }

        return new Builder($entity);
    }

    /**
     * Get entity storage status.
     *
     * @param Entity $entity
     *
     * @return int|null
     */
    public function getStatus(Entity $entity): ?int
    {
        if (!$this->storage->contains($entity)) {
            return null;
        }

        return $this->storage[$entity];
    }

    /**
     * Save entity.
     *
     * @param Entity $entity
     * @param bool $persist Persist entity immediately
     *
     * @throws OrmException
     */
    public function save(Entity $entity, bool $persist = false): void
    {
        $status = EntityStorage::STATUS_TO_INSERT;
        if ($this->storage->contains($entity)) {
            $status = EntityStorage::STATUS_TO_UPDATE;
        }

        $this->storage->attach($entity, $status);

        if ($persist) {
            $this->persistEntity($entity);
        }
    }

    /**
     * Delete entity.
     *
     * @param Entity $entity
     * @param bool $persist Persist entity immediately
     *
     * @throws OrmException
     */
    public function delete(Entity $entity, bool $persist = false): void
    {
        if (!$this->storage->contains($entity)) {
            throw new OrmException('Entity does not exists in storage');
        }

        $this->storage->attach($entity, EntityStorage::STATUS_TO_DELETE);

        if ($persist) {
            $this->persistEntity($entity);
        }
    }

    /**
     * Refresh entity.
     *
     * @param Entity $entity
     *
     * @throws OrmException
     */
    public function refresh(Entity $entity): void
    {
        $this->getMapper($entity)->refreshEntity($entity);
        $this->storage->attach($entity, EntityStorage::STATUS_NONE);
    }

    /**
     * Persist all waiting entity in storage.
     *
     * @throws OrmException
     * @throws Throwable
     */
    public function persist(): void
    {
        try {
            $this->connections->beginTransaction();

            /** @var Entity $entity */
            foreach ($this->storage as $entity) {
                $this->persistEntity($entity);
            }

            $this->connections->commit();
        } catch (OrmException $exception) {
            $this->connections->rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            $this->connections->rollBack();

            throw new OrmException('Error while persisting entities', previous: $exception);
        }
    }

    /**
     * Persist an entity.
     *
     * @param Entity $entity
     *
     * @throws OrmException
     */
    private function persistEntity(Entity $entity): void
    {
        $status = $this->storage[$entity];

        // Nothing to do
        if ($status === EntityStorage::STATUS_NONE) {
            return;
        }

        // Get mapper of entity
        $mapper = $this->getMapper($entity);

        switch ($status) {
            case EntityStorage::STATUS_TO_DELETE:
                /** @var Event\EntityBeforeDeleteEvent $event */
                $event = $this->eventDispatcher->dispatch(new Event\EntityBeforeDeleteEvent($entity));

                if (!$event->isPropagationStopped()) {
                    $mapper->deleteEntity($entity);

                    $this->storage->detach($entity);

                    $this->eventDispatcher->dispatch(new Event\EntityAfterDeleteEvent($entity));
                }
                break;
            case EntityStorage::STATUS_TO_INSERT:
                /** @var Event\EntityBeforeDeleteEvent $event */
                $event = $this->eventDispatcher->dispatch(new Event\EntityBeforeSaveEvent($entity));

                if (!$event->isPropagationStopped()) {
                    $mapper->insertEntity($entity);

                    $this->storage->attach($entity, EntityStorage::STATUS_NONE);

                    $this->eventDispatcher->dispatch(new Event\EntityAfterSaveEvent($entity));
                }
                break;
            case EntityStorage::STATUS_TO_UPDATE:
                /** @var Event\EntityBeforeDeleteEvent $event */
                $event = $this->eventDispatcher->dispatch(new Event\EntityBeforeSaveEvent($entity, true));

                if (!$event->isPropagationStopped()) {
                    $mapper->updateEntity($entity);

                    $this->storage->attach($entity, EntityStorage::STATUS_NONE);

                    $this->eventDispatcher->dispatch(new Event\EntityAfterSaveEvent($entity, true));
                }
                break;
        }
    }
}