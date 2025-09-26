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
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\OrmException;
use ReflectionNamedType;
use ValueError;

class GenericMapper extends AbstractMapper
{
    /**
     * @inheritDoc
     */
    public function hydrateEntity(Entity $entity, array $data): void
    {
        $this->assertEntityType($entity, $this->reflection->class);

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

                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($entity, $value);
            }
        } catch (OrmException|ValueError $e) {
            throw new MapperException(sprintf('Unable to hydrate entity "%s"', $this->reflection->class), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function collectEntity(Entity $entity, ?array $columns = null): array
    {
        $this->assertEntityType($entity, $this->reflection->class);

        try {
            // Init data with columns
            $data = array_fill_keys($columns ?? $this->reflection->getTable()->getColumnsName(), null);

            foreach ($data as $column => &$value) {
                // Not attempted column
                if (null !== $columns && !in_array($column, $columns)) {
                    continue;
                }

                // Entity doesn't have property
                if (!$this->reflection->getClass()->hasProperty($column)) {
                    continue;
                }

                $reflectionProperty = $this->reflection->getProperty($column);

                if (false === $reflectionProperty->isInitialized($entity)) {
                    continue;
                }

                $value = $reflectionProperty->getValue($entity);

                if (null !== $value) {
                    // Get type
                    $reflectionType = $reflectionProperty->getType();

                    // Convert type
                    if ($reflectionType instanceof ReflectionNamedType) {
                        $value = $this->reflection->getType($column)->toSchema(
                            $value,
                            ExpectedType::fromReflection($reflectionType)
                        );
                    }
                }
            }

            return $data;
        } catch (OrmException|ValueError $e) {
            throw new MapperException(sprintf('Unable to collect entity "%s"', $this->reflection->class), 0, $e);
        }
    }
}
