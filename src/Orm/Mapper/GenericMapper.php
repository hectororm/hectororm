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

use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\TypeException;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\OrmException;
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
            foreach ($this->reflection->getTable()->getColumnsName() as $column) {
                // Column doesn't exists in data
                if (!array_key_exists($column, $data)) {
                    continue;
                }

                // Entity doesn't have property
                if (!$this->reflection->getClass()->hasProperty($column)) {
                    continue;
                }

                $reflectionProperty = $this->reflection->getProperty($column);
                $value = $data[$column];

                if (null !== $value) {
                    // Get type
                    $reflectionType = $reflectionProperty->getType();

                    // Convert type
                    if ($reflectionType instanceof ReflectionNamedType) {
                        $value = $this->reflection->getType($column)->fromSchema(
                            $value,
                            ExpectedType::fromReflection($reflectionType)
                        );
                    }
                }

                $reflectionProperty->setValue($entity, $value);
            }

            $this->setPivotData($entity, $data);
        } catch (OrmException | TypeException $e) {
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

            foreach ($this->reflection->getTable()->getColumnsName() as $column) {
                // Not attempted column
                if (null !== $columns && !in_array($column, $columns)) {
                    continue;
                }

                // Entity doesn't have property
                if (!$this->reflection->getClass()->hasProperty($column)) {
                    continue;
                }

                // Set default NULL value
                $data[$column] = null;

                $reflectionProperty = $this->reflection->getProperty($column);

                if ($reflectionProperty->isInitialized($entity)) {
                    $value = $reflectionProperty->getValue($entity);

                    if (null !== $value) {
                        // Get type
                        $reflectionType = $reflectionProperty->getType();

                        // Convert type
                        if ($reflectionType instanceof ReflectionNamedType) {
                            $data[$column] = $this->reflection->getType($column)->toSchema(
                                $value,
                                ExpectedType::fromReflection($reflectionType)
                            );
                        }
                    }
                }
            }

            return $data;
        } catch (OrmException | TypeException $e) {
            throw new MapperException(sprintf('Unable to collect entity "%s"', $this->reflection->class), 0, $e);
        }
    }
}