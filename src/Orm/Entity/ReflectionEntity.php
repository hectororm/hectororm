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

namespace Hector\Orm\Entity;

use Exception;
use Hector\DataTypes\Type\TypeInterface;
use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Attributes;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Mapper\Mapper;
use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\Exception\NotFoundException;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Table;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * @property-read string|Entity $class Class of entity
 * @property-read string $mapper Mapper class of entity
 * @property-read string $collection Collection class of entity
 * @property-read string $table Table name of entity
 * @property-read string|null $schema Schema name of entity
 * @property-read string|null $connection Connection name of entity
 * @property-read array $types Type of columns
 * @property-read array $hidden Hidden columns
 *
 * @template T
 */
class ReflectionEntity
{
    use EntityAssert;

    private static array $reflections = [];

    private string $entity;
    private string $mapper;
    private string $table;
    private ?string $schema;
    private ?string $connection;
    private array $types = [];
    private array $hidden = [];

    private ?Table $tableInstance = null;
    private Mapper $mapperInstance;
    private ReflectionClass $reflection;

    /**
     * Get entity reflection.
     *
     * @param Entity|string $entity
     *
     * @return static
     * @throws OrmException
     */
    public static function get(Entity|string $entity): static
    {
        if ($entity instanceof Entity) {
            $entity = $entity::class;
        }

        if (array_key_exists($entity, static::$reflections)) {
            return static::$reflections[$entity];
        }

        return static::$reflections[$entity] = new ReflectionEntity($entity);
    }

    /**
     * EntityProperties constructor.
     *
     * @param Entity|class-string<T> $entity
     *
     * @throws OrmException
     */
    public function __construct(Entity|string $entity)
    {
        $this->assertEntity($entity);
        $this->entity = ($entity instanceof Entity ? $entity::class : $entity);

        $this->mapper = $this->retrieveMapper();
        list(
            'table' => $this->table,
            'schema' => $this->schema,
            'connection' => $this->connection
            ) = $this->retrieveTable();
        $this->types = $this->retrieveTypes();
        $this->hidden = $this->retrieveHidden();
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'entity' => $this->entity,
            'mapper' => $this->mapper,
            'table' => $this->table,
            'schema' => $this->schema,
            'connection' => $this->connection,
            'types' => $this->types,
            'hidden' => $this->hidden,
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
        $this->entity = $data['entity'] ?? throw new OrmException('Unable to unserialize ReflectionEntity');
        $this->mapper = $data['mapper'] ?? throw new OrmException('Unable to unserialize ReflectionEntity');
        $this->table = $data['table'] ?? throw new OrmException('Unable to unserialize ReflectionEntity');
        $this->schema = $data['schema'] ?? null;
        $this->connection = $data['connection'] ?? null;
        $this->types = $data['types'] ?? [];
        $this->hidden = $data['hidden'] ?? [];
    }

    ////////////////
    /// RETRIEVE ///
    ////////////////

    /**
     * Retrieve types of columns.
     *
     * @return array
     * @throws OrmException
     */
    protected function retrieveTypes(): array
    {
        $types = [];
        $reflectionClass = $this->getClass();

        do {
            $attributes = $reflectionClass->getAttributes(
                Attributes\Type::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($attributes as $attribute) {
                $typeAttribute = $attribute->newInstance();
                $types[$typeAttribute->column] = new $typeAttribute->type(...$typeAttribute->arguments);
            }
        } while ($reflectionClass = $reflectionClass->getParentClass());

        return $types;
    }

    /**
     * Retrieve hidden columns.
     *
     * @return array
     * @throws OrmException
     */
    protected function retrieveHidden(): array
    {
        $hidden = [];
        $reflectionClass = $this->getClass();

        do {
            $attributes = $reflectionClass->getAttributes(
                Attributes\Hidden::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($attributes as $attribute) {
                $hidden = array_merge($attribute->newInstance()->columns, $hidden);
            }
        } while ($reflectionClass = $reflectionClass->getParentClass());

        return $hidden;
    }

    /**
     * Retrieve mapper class.
     *
     * @return string
     * @throws OrmException
     */
    protected function retrieveMapper(): string
    {
        $reflectionClass = $this->getClass();
        $mapper = null;

        do {
            $attributes = $reflectionClass->getAttributes(
                Attributes\Mapper::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            if (count($attributes) === 1) {
                $mapper = reset($attributes)->newInstance()->mapper;
            }
        } while (null === $mapper && $reflectionClass = $reflectionClass->getParentClass());

        if (!is_a($mapper, Mapper::class, true)) {
            throw new OrmException(
                sprintf(
                    'Defined mapper of "%s" entity must implement "%s" interface',
                    $this->entity,
                    Mapper::class
                )
            );
        }

        return (string)$mapper;
    }

    /**
     * Retrieve table.
     *
     * @return string[]
     * @throws OrmException
     */
    protected function retrieveTable(): array
    {
        $reflectionClass = $this->getClass();
        $tableAttribute = null;

        do {
            $attributes = $reflectionClass->getAttributes(
                Attributes\Table::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            if (count($attributes) === 1) {
                /** @var Attributes\Table $tableAttribute */
                $tableAttribute = reset($attributes)->newInstance();
            }
        } while (null === $tableAttribute && $reflectionClass = $reflectionClass->getParentClass());

        // Deduct table name with class name
        if (null === $tableAttribute) {
            $tableName = substr($this->entity, strrpos($this->entity, '\\') + 1);
            if (false !== ($dotPos = strrpos($tableName, '.'))) {
                $tableName = substr($tableName, 0, $dotPos);
            }
            $tableName = b_snake_case($tableName);

            $tableAttribute = new Attributes\Table($tableName);
        }

        return [
            'table' => $tableAttribute->table,
            'schema' => $tableAttribute->schema,
            'connection' => $tableAttribute->connection,
        ];
    }

    ///////////////
    /// GETTERS ///
    ///////////////

    /**
     * __get PHP magic method.
     *
     * @param string $name
     *
     * @return string|array
     * @throws OrmException|SchemaException
     */
    public function __get(string $name): string|array
    {
        return match ($name) {
            'class' => $this->entity,
            'mapper' => $this->mapper,
            'table' => $this->getTable()->getName(),
            'schema' => $this->getTable()->getSchemaName(),
            'connection' => $this->getTable()->getSchema()->getConnection(),
            'hidden' => $this->hidden,
        };
    }

    /**
     * Get entity class name.
     *
     * @return class-string<T>
     */
    public function getName(): string
    {
        return $this->entity;
    }

    /**
     * Get mapper class name.
     *
     * @return string
     */
    public function getMapperName(): string
    {
        return $this->mapper;
    }

    /**
     * Get reflection of class.
     *
     * @return ReflectionClass
     * @throws OrmException
     */
    public function getClass(): ReflectionClass
    {
        try {
            return $this->reflection ?? $this->reflection = new ReflectionClass($this->entity);
        } catch (ReflectionException $e) {
            throw new OrmException(sprintf('Unable to do reflection of "%s" entity class', $this->entity), 0, $e);
        }
    }

    /**
     * Get property.
     *
     * @param string $name
     * @param string|null $class
     *
     * @return ReflectionProperty
     * @throws OrmException
     */
    public function getProperty(string $name, ?string $class = null): ReflectionProperty
    {
        try {
            if (null === $class) {
                $propertyReflection = $this->getClass()->getProperty($name);
                $propertyReflection->setAccessible(true);

                return $propertyReflection;
            }

            $propertyReflection = new ReflectionProperty($class, $name);
            $propertyReflection->setAccessible(true);

            return $propertyReflection;
        } catch (ReflectionException $e) {
            throw new OrmException(sprintf('Unable to get property "%s" of entity "%s"', $name, $this->entity), 0, $e);
        }
    }

    /**
     * Get Hector data.
     *
     * @param Entity $entity
     *
     * @return EntityData
     */
    public function getHectorData(Entity $entity): EntityData
    {
        $classReflection = new ReflectionClass(Entity::class);
        $propertyReflection = $classReflection->getProperty('_hectorData');
        $propertyReflection->setAccessible(true);

        if ($propertyReflection->isInitialized($entity)) {
            return $propertyReflection->getValue($entity);
        }

        $hectorData = new EntityData($entity);
        $propertyReflection->setValue($entity, $hectorData);

        return $hectorData;
    }

    /**
     * Get table.
     *
     * @return Table
     * @throws OrmException
     */
    public function getTable(): Table
    {
        try {
            return
                $this->tableInstance ??
                Orm::get()->getSchemaContainer()->getTable($this->table, $this->schema, $this->connection);
        } catch (NotFoundException $e) {
            throw new OrmException(
                sprintf('Table "%s" not found for entity "%s"', $this->table, $this->entity),
                0,
                $e
            );
        }
    }

    /**
     * Get mapper.
     *
     * @return Mapper
     * @throws OrmException
     */
    public function getMapper(): Mapper
    {
        return $this->mapperInstance ?? $this->mapperInstance = Orm::get()->getMapper($this->entity);
    }

    /**
     * Get hidden columns.
     *
     * @return array
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Get type.
     *
     * @param string $column
     *
     * @return TypeInterface|null
     * @throws OrmException
     */
    public function getType(string $column): ?TypeInterface
    {
        try {
            if (array_key_exists($column, $this->types)) {
                return $this->types[$column];
            }

            return Orm::get()->getTypes()->get($this->getTable()->getColumn($column)->getType());
        } catch (Exception $exception) {
            throw new OrmException(sprintf('Type error for column %s', $column), previous: $exception);
        }
    }

    /////////////////
    /// INSTANCES ///
    /////////////////

    public function newInstance(): Entity
    {
        return new $this->entity();
    }

    /**
     * New instance of mapper.
     *
     * @param EntityStorage $storage
     *
     * @return Mapper
     */
    public function newInstanceOfMapper(EntityStorage $storage): Mapper
    {
        return new $this->mapper($this->entity, $storage);
    }
}