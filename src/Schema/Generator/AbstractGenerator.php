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

namespace Hector\Schema\Generator;

use Hector\Connection\Connection;
use Hector\Schema\Column;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Schema;
use Hector\Schema\SchemaContainer;
use Hector\Schema\Table;
use ReflectionClass;
use ReflectionException;

/**
 * Class AbstractGenerator.
 *
 * @package Hector\Schema\Generator
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    /** @var ReflectionClass[] */
    private static array $classReflections;
    protected Connection $connection;

    /**
     * AbstractGenerator constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get schema info.
     *
     * @param string $name
     *
     * @return array
     */
    abstract protected function getSchemaInfo(string $name): array;

    /**
     * Get tables info.
     *
     * @param string $name
     *
     * @return array[array]
     */
    abstract protected function getTablesInfo(string $name): array;

    /**
     * Get columns info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array[array]
     */
    abstract protected function getColumnsInfo(string $schema, string $table): array;

    /**
     * Get indexes info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array
     */
    abstract protected function getIndexesInfo(string $schema, string $table): array;

    /**
     * Get foreign keys info.
     *
     * @param string $schema
     * @param string $table
     *
     * @return array
     */
    abstract protected function getForeignKeysInfo(string $schema, string $table): array;

    /**
     * Hydrate properties of object.
     *
     * @param object $object
     * @param $values
     *
     * @throws ReflectionException
     */
    private function hydrateObject(object $object, $values): void
    {
        $class = get_class($object);
        if (!isset(self::$classReflections[$class])) {
            self::$classReflections[$class] = new ReflectionClass($class);
        }

        foreach (self::$classReflections[$class]->getProperties() as $property) {
            if (!array_key_exists($property->getName(), $values)) {
                continue;
            }

            $property->setAccessible(true);
            $property->setValue($object, $values[$property->getName()]);
        }
    }

    /**
     * @inheritDoc
     */
    public function generateSchema(string $name): Schema
    {
        try {
            $schema = new Schema();

            // Get schema info
            $schemaInfo = $this->getSchemaInfo($name);
            $schemaInfo['connection'] = $this->connection->getName();
            $this->hydrateObject($schema, $schemaInfo);

            $tables = [];
            foreach ($this->getTablesInfo($name) as $tableInfo) {
                $table = new Table();
                $this->hydrateObject($table, $tableInfo);
                $tables[$table->getName()] = $table;

                $columns = [];
                foreach ($this->getColumnsInfo($name, $table->getName()) as $columnInfo) {
                    $column = new Column();
                    $this->hydrateObject($column, $columnInfo);
                    $columns[$column->getName()] = $column;
                }

                $indexes = [];
                foreach ($this->getIndexesInfo($name, $table->getName()) as $indexInfo) {
                    $index = new Index();
                    $this->hydrateObject($index, $indexInfo);
                    $indexes[$index->getName()] = $index;
                }

                $foreignKeys = [];
                foreach ($this->getForeignKeysInfo($name, $table->getName()) as $foreignKeyInfo) {
                    $foreignKey = new ForeignKey();
                    $this->hydrateObject($foreignKey, $foreignKeyInfo);
                    $foreignKeys[$foreignKey->getName()] = $foreignKey;
                }

                $this->hydrateObject(
                    $table,
                    [
                        'columns' => $columns,
                        'indexes' => $indexes,
                        'foreign_keys' => $foreignKeys,
                    ]
                );
                $table->restoreInheritance();
            }

            $this->hydrateObject($schema, ['tables' => $tables]);
            $schema->restoreInheritance();

            return $schema;
        } catch (ReflectionException $e) {
            throw new SchemaException(sprintf('Unable to generate schema of "%s"', $name), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function generateSchemas(string ...$names): SchemaContainer
    {
        $container = new SchemaContainer();
        $schemas = [];

        try {
            foreach ($names as $name) {
                $schemas[] = $schema = $this->generateSchema($name);
            }

            $this->hydrateObject($container, ['schemas' => $schemas]);
            $container->restoreInheritance();
        } catch (SchemaException $e) {
            throw $e;
        } catch (ReflectionException $e) {
            throw new SchemaException(
                sprintf(
                    'Unable to generate schema of %s',
                    implode(
                        ', ',
                        array_map(fn($name) => sprintf('"%s"', $name), $names)
                    )
                ),
                0,
                $e
            );
        }

        return $container;
    }
}