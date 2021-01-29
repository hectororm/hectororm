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
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Orm;
use ReflectionException;
use ReflectionNamedType;

class GenericMapper extends AbstractMapper
{
    /**
     * @inheritDoc
     */
    public function hydrateEntity(Entity $entity, array $data): void
    {
        if (!is_a($entity, $this->reflection->class, true)) {
            throw new MapperException(sprintf('Entity must be a "%s" class', $this->reflection->class));
        }

        // No data
        if (empty($data)) {
            return;
        }

        try {
            foreach ($this->reflection->getTable()->getColumns() as $column) {
                // Column doesn't exists in data
                if (!array_key_exists($column->getName(), $data)) {
                    continue;
                }

                // Entity doesn't have property
                if (!$this->reflection->getClass()->hasProperty($column->getName())) {
                    continue;
                }

                $reflectionProperty = $this->reflection->getProperty($column->getName());
                $value = $data[$column->getName()];

                if (null !== $value) {
                    // Get type
                    $reflectionType = $reflectionProperty->getType();

                    if ($reflectionType instanceof ReflectionNamedType) {
                        // Convert type
                        $value = Orm::get()
                            ->getDataTypes()
                            ->getTypeForColumn($column)
                            ->fromSchema($value, $reflectionType);
                    }
                }

                $reflectionProperty->setValue($entity, $value);
            }

//            $this->reflection->getHectorData($entity)->setPivot($data);
            $this->setPivotData($entity, $data);
        } catch (ReflectionException $e) {
            throw new MapperException(sprintf('Unable to hydrate entity "%s"', $this->reflection->class), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function collectEntity(Entity $entity, ?array $columns = null): array
    {
        if (!is_a($entity, $this->reflection->class, true)) {
            throw new MapperException(sprintf('Entity must be a "%s" class', $this->reflection->class));
        }

        try {
            $data = [];

            foreach ($this->reflection->getTable()->getColumns() as $column) {
                // Not attempted column
                if (null !== $columns && !in_array($column->getName(), $columns)) {
                    continue;
                }

                // Entity doesn't have property
                if (!$this->reflection->getClass()->hasProperty($column->getName())) {
                    continue;
                }

                // Set default NULL value
                $data[$column->getName()] = null;

                $reflectionProperty = $this->reflection->getProperty($column->getName());

                if ($reflectionProperty->isInitialized($entity)) {
                    $value = $reflectionProperty->getValue($entity);

                    if (null !== $value) {
                        // Get type
                        $reflectionType = $reflectionProperty->getType();

                        if ($reflectionType instanceof ReflectionNamedType) {
                            $data[$column->getName()] = Orm::get()
                                ->getDataTypes()
                                ->getTypeForColumn($column)
                                ->toSchema($value, $reflectionType);
                        }
                    }
                }
            }

            return $data;
        } catch (ReflectionException $e) {
            throw new MapperException(sprintf('Unable to collect entity "%s"', $this->reflection->class), 0, $e);
        }
    }
}