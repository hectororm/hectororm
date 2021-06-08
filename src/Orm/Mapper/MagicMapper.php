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

use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\Exception\SchemaException;

class MagicMapper extends AbstractMapper
{
    /**
     * @inheritDoc
     */
    public function __construct(string $entity, protected EntityStorage $storage)
    {
        // Not a valid entity
        if (!is_a($entity, MagicEntity::class, true)) {
            throw new MapperException(sprintf('"%s" must extends "%s" class', $entity, MagicEntity::class));
        }

        parent::__construct($entity, $storage);
    }

    /**
     * @inheritDoc
     */
    public function hydrateEntity(Entity $entity, array $data): void
    {
        if (!$entity instanceof $this->reflection->class) {
            throw new MapperException(sprintf('Entity must be a "%s" class', $this->reflection->class));
        }

        // No data
        if (empty($data)) {
            return;
        }

        try {
            // Update pivot data
            $this->setPivotData($entity, $data);

            // Filter bad properties
            $data = array_filter(
                $data,
                fn($key) => $this->reflection->getTable()->hasColumn($key),
                ARRAY_FILTER_USE_KEY
            );

            // Convert types
            array_walk(
                $data,
                function (&$value, $key) {
                    if (null === $value) {
                        return;
                    }

                    $column = $this->reflection->getTable()->getColumn($key);
                    $value = Orm::get()->getDataTypes()->getTypeForColumn($column)->fromSchema($value);
                }
            );

            $reflectionProperty = $this->reflection->getProperty('hectorAttributes', MagicEntity::class);

            $propertyData = $reflectionProperty->getValue($entity);
            $propertyData = array_replace($propertyData, $data);
            $reflectionProperty->setValue($entity, $propertyData);
        } catch (OrmException | SchemaException $e) {
            throw new MapperException(sprintf('Unable to hydrate entity "%s"', $this->reflection->class), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function collectEntity(Entity $entity, ?array $columns = null): array
    {
        if (!$entity instanceof $this->reflection->class) {
            throw new MapperException(sprintf('Entity must be a "%s" class', $this->reflection->class));
        }

        try {
            $reflectionDataProperty = $this->reflection->getProperty('hectorAttributes', MagicEntity::class);
            $data = $reflectionDataProperty->getValue($entity);

            // Remove columns not attempted
            if (null !== $columns) {
                $data = array_intersect_key($data, array_fill_keys($columns, null));
            }

            // Convert types
            array_walk(
                $data,
                function (&$value, $key) {
                    if (null === $value) {
                        return;
                    }

                    $column = $this->reflection->getTable()->getColumn($key);
                    $value = Orm::get()->getDataTypes()->getTypeForColumn($column)->toSchema($value);
                }
            );

            // Filter columns
            return array_filter(
                $data,
                function ($value, $key) {
                    $column = $this->reflection->getTable()->getColumn($key);

                    // Not null value
                    if (null !== $value) {
                        return true;
                    }

                    // Keep nullable column
                    if ($column->isNullable()) {
                        return true;
                    }

                    // Remove column, because can be an autoincrement
                    // column or a column with default value
                    return false;
                },
                ARRAY_FILTER_USE_BOTH
            );
        } catch (OrmException | SchemaException $e) {
            throw new MapperException(sprintf('Unable to collect entity "%s"', $this->reflection->class), 0, $e);
        }
    }
}