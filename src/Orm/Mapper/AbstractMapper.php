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

namespace Hector\Orm\Mapper;

use Generator;
use Hector\Orm\Attributes;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Storage\EntityStorage;
use ReflectionAttribute;

abstract class AbstractMapper implements Mapper
{
    protected ReflectionEntity $reflection;
    protected ?Relationships $relationships = null;

    /**
     * Create mapper.
     *
     * @param string $entity
     * @param EntityStorage $storage
     *
     * @throws OrmException
     */
    public function __construct(
        string $entity,
        protected EntityStorage $storage
    ) {
        $this->reflection = Orm::get()->getEntityReflection($entity);
    }

    /**
     * PHP serialize method.
     *
     * @return array
     * @throws OrmException
     */
    public function __serialize(): array
    {
        throw new OrmException('Serialization of mapper is not allowed');
    }

    /**
     * @inheritDoc
     */
    public function getRelationships(): Relationships
    {
        if (null !== $this->relationships) {
            return $this->relationships;
        }

        // Init relationships
        $this->relationships = new Relationships($this->reflection->class);

        $attributes =
            $this->reflection->getClass()
                ->getAttributes(
                    Attributes\RelationshipAttribute::class,
                    ReflectionAttribute::IS_INSTANCEOF
                );

        foreach ($attributes as $attribute) {
            /** @var Attributes\RelationshipAttribute $attrRelationship */
            $attrRelationship = $attribute->newInstance();
            $attrRelationship->init($this->relationships);
        }

        return $this->relationships;
    }

    /**
     * Get primary value.
     *
     * @throws OrmException
     */
    public function getPrimaryValue(Entity $entity): ?array
    {
        $primaryIndex = $this->reflection->getTable()->getPrimaryIndex();

        if (null === $primaryIndex) {
            return null;
        }

        $collected = $this->collectEntity($entity, $this->reflection->getTable()->getPrimaryIndex()->getColumnsName());

        return $collected ?: null;
    }

    ////////////
    /// HASH ///
    ////////////

    /**
     * Get hash of primary values.
     *
     * @param Entity $entity
     *
     * @return string
     * @throws OrmException
     */
    public function getPrimaryHash(Entity $entity): string
    {
        return md5(
            implode(
                "\0",
                $this->collectEntity($entity, $this->reflection->getTable()->getPrimaryIndex()->getColumnsName())
            )
        );
    }

    /**
     * Get hash of entity data.
     *
     * @param Entity $entity
     *
     * @return string
     * @throws OrmException
     */
    public function getDataHash(Entity $entity): string
    {
        return md5(implode("\0", $this->collectEntity($entity)));
    }

    /////////////
    /// FETCH ///
    /////////////

    /**
     * @inheritDoc
     */
    public function fetchOneWithBuilder(Builder $builder): ?Entity
    {
        $data = $builder->fetchOne();

        if (null === $data) {
            return null;
        }

        $entity = new $this->reflection->class();
        $this->hydrateEntity($entity, $data);
        $this->updateOriginalData($entity, $data, true);
        $this->storage->attach($entity);

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function fetchAllWithBuilder(Builder $builder): Collection
    {
        // Collection
        $collection = $this->reflection->newInstanceOfCollection(iterator_to_array($this->yieldWithBuilder($builder)));

        // With
        $collection->load($builder->with);

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function yieldWithBuilder(Builder $builder): Generator
    {
        foreach ($builder->fetchAll() as $data) {
            $entity = new $this->reflection->class();
            $this->hydrateEntity($entity, $data);
            $this->updateOriginalData($entity, $data, true);
            $this->storage->attach($entity);

            yield $entity;
        }
    }

    ////////////////////////////////
    /// INSERT / UPDATE / DELETE ///
    ////////////////////////////////

    /**
     * @inheritDoc
     */
    public function insertEntity(Entity $entity): int
    {
        $entity->getRelated()->linkForeign();

        // Collect data from entity and filter NULL data
        $collected = $this->collectEntity($entity);
        $collected = array_filter(
            $collected,
            function ($value, $key) {
                $table = $this->reflection->getTable();

                if (!$table->hasColumn($key)) {
                    return false;
                }

                $column = $table->getColumn($key);

                if (null === $value) {
                    // Remove null value on auto increment column
                    if ($column->isAutoIncrement()) {
                        return false;
                    }

                    // Remove null value on column with default value
                    if (null !== $column->getDefault()) {
                        return false;
                    }
                }

                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $nbAffected = $entity::query()->insert($this->quoteArrayKeys($collected));

        if ($nbAffected === 1) {
            if (null !== ($autoIncrementColumn = $this->reflection->getTable()->getAutoIncrementColumn())) {
                $this->hydrateEntity(
                    $entity,
                    [
                        $autoIncrementColumn->getName() =>
                            (int)Orm::get()->getConnection($this->reflection->connection)->getLastInsertId()
                    ]
                );
            }
        }

        $entity->getRelated()->linkNative();

        $this->refreshEntity($entity);

        return $nbAffected;
    }

    /**
     * @inheritDoc
     */
    public function updateEntity(Entity $entity): int
    {
        $entity->getRelated()->linkForeign();

        $values = $this->collectEntity($entity, $this->getEntityAlteration($entity));
        $primaryValue = $this->getPrimaryValue($entity);

        if (count($values) > 0) {
            // Separate values of primary value
            $values = array_diff_key($values, array_flip($primaryValue));

            $nbAffected =
                $entity::query()
                    ->whereEquals($this->quoteArrayKeys($primaryValue))
                    ->update($this->quoteArrayKeys($values));
        }

        $entity->getRelated()->linkNative();

        $this->refreshEntity($entity);

        return $nbAffected ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function deleteEntity(Entity $entity): int
    {
        $values = $this->collectEntity($entity);
        $primaryValue = $this->extractPrimaryValue($values);

        $affected = $entity::query()->whereEquals($this->quoteArrayKeys($primaryValue))->delete();

        if ($affected !== 0) {
            $this->updateOriginalData($entity, [], true);
        }

        return $affected;
    }

    /**
     * @inheritDoc
     */
    public function refreshEntity(Entity $entity): void
    {
        $values = $this->collectEntity($entity);
        $primaryValue = $this->extractPrimaryValue($values);

        $data = $entity::query()->whereEquals($this->quoteArrayKeys($primaryValue))->fetchOne();

        if (null === $data) {
            throw new MapperException('Unable to refresh an unexciting entity');
        }

        $this->hydrateEntity($entity, $data);
        $this->updateOriginalData($entity, $data, true);
        $this->storage->attach($entity);
    }

    /**
     * @inheritDoc
     */
    public function getEntityAlteration(Entity $entity, ?array $columns = null): array
    {
        $originalData = $this->reflection->getHectorData($entity)->get('original', []);
        if (null !== $columns) {
            $originalData = array_intersect_key($originalData, array_fill_keys($columns, null));
        }

        $currentData = $this->collectEntity($entity, $columns);

        ksort($originalData);
        ksort($currentData);

        return array_keys(array_diff_assoc($originalData, $currentData));
    }

    /////////////
    /// PIVOT ///
    /////////////

    /**
     * Get pivot data.
     *
     * @param Entity $entity
     *
     * @return array
     */
    public function getPivotData(Entity $entity): array
    {
        return $this->reflection->getHectorData($entity)->get('pivot', []);
    }

    /**
     * Set pivot data.
     *
     * @param Entity $entity
     * @param array $data
     * @param bool $merge
     *
     */
    protected function setPivotData(Entity $entity, array $data, bool $merge = true): void
    {
        $pivotData = array_filter($data, fn($key) => str_starts_with($key, 'PIVOT_'), ARRAY_FILTER_USE_KEY);

        $pivotKeys = array_keys($pivotData);
        array_walk($pivotKeys, fn(&$key) => $key = substr($key, 6));
        $pivotData = array_combine($pivotKeys, array_values($pivotData));

        // Merge pivot data
        if ($merge) {
            $pivotData = array_replace($this->getPivotData($entity), $pivotData);
        }

        $this->reflection->getHectorData($entity)->set('pivot', $pivotData);
    }

    ///////////////
    /// HELPERS ///
    ///////////////

    /**
     * Update original data.
     *
     * @param Entity $entity
     * @param array $data
     * @param bool $erase
     */
    protected function updateOriginalData(Entity $entity, array $data, bool $erase = false): void
    {
        // Erase old data
        if (!$erase) {
            $data = array_replace($this->reflection->getHectorData($entity)->get('original', []), $data);
        }

        $this->reflection->getHectorData($entity)->set('original', $data);
    }

    /**
     * Extract primary value of entity values.
     *
     * @param array $values
     *
     * @return array
     * @throws OrmException
     */
    private function extractPrimaryValue(array $values): array
    {
        $values = array_filter($values, fn($value) => null !== $value);
        $primaryColumns = $this->reflection->getTable()->getPrimaryIndex()->getColumnsName();
        $primary = array_intersect_key($values, array_flip($primaryColumns));

        if (count($primary) !== count($primaryColumns)) {
            $missing = array_diff_key(array_flip($primaryColumns), $primary);

            throw new MapperException(
                sprintf(
                    'Primary value of entity is not valid, missing column "%s"',
                    implode(', ', array_keys($missing))
                )
            );
        }

        return $primary;
    }

    /**
     * Quote array keys.
     *
     * @param array $values
     *
     * @return array
     */
    protected function quoteArrayKeys(array $values): array
    {
        $keys = $this->quoteArrayValues(array_keys($values));

        return array_combine($keys, array_values($values));
    }

    /**
     * Quote array values.
     *
     * @param array $values
     *
     * @return array
     */
    protected function quoteArrayValues(array $values): array
    {
        array_walk($values, fn(&$value) => $value = sprintf('`%s`', $value));

        return $values;
    }
}