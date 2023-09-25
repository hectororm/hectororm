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

namespace Hector\Orm\Relationship;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\PivotData;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Entity\Related;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Row;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Table;

class ManyToMany extends Relationship
{
    use ValidToManyTrait;

    protected string $pivotTable;
    protected array $pivotTargetColumns;
    protected array $pivotSourceColumns;

    /**
     * ManyToMany constructor.
     *
     * @param string $name
     * @param string $sourceEntity
     * @param string $targetEntity
     * @param string|null $pivotTable
     * @param array|null $columnsFrom
     * @param array|null $columnsTo
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        ?string $pivotTable = null,
        ?array $columnsFrom = null,
        ?array $columnsTo = null
    ) {
        parent::__construct($name, $sourceEntity, $targetEntity);

        // Resolve pivot table name
        $pivotTable = $this->resolvePivotTable($pivotTable);
        $this->pivotTable = $pivotTable->getName();

        // Deduct default columns
        if (null === $columnsFrom) {
            $this->sourceColumns = $this->sourceEntity->getPrimaryIndex()?->getColumnsName();
            $this->pivotTargetColumns = $this->sourceColumns ?? [];
        }
        if (null === $columnsTo) {
            $this->pivotSourceColumns = $this->targetEntity->getPrimaryIndex()?->getColumnsName();
            $this->targetColumns = $this->pivotSourceColumns ?? [];
        }

        // Defined columns
        if (null !== $columnsFrom) {
            $this->sourceColumns = array_keys($columnsFrom);
            $this->pivotTargetColumns = array_values($columnsFrom);
        }
        if (null !== $columnsTo) {
            $this->pivotSourceColumns = array_keys($columnsTo);
            $this->targetColumns = array_values($columnsTo);
        }
    }

    /**
     * Resolve pivot table name.
     *
     * @param string|null $table
     *
     * @return Table
     * @throws OrmException
     */
    private function resolvePivotTable(?string $table): Table
    {
        if (null === $table) {
            $table = sprintf('%s_%s', $this->sourceEntity->table, $this->targetEntity->table);
        }

        try {
            return $this->sourceEntity->getTable()->getSchema()->getTable($table);
        } catch (SchemaException $exception) {
            throw new OrmException(
                sprintf('Unable to resolve pivot table of relation "%s" of table "%s"', $this->name, $table),
                previous: $exception
            );
        }
    }

    /**
     * Get pivot table.
     *
     * @return string
     */
    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    /**
     * Get second source columns.
     *
     * @return array
     */
    public function getPivotSourceColumns(): array
    {
        return $this->pivotSourceColumns;
    }

    /**
     * Get second target columns.
     *
     * @return array
     */
    public function getPivotTargetColumns(): array
    {
        return $this->pivotTargetColumns;
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    protected function newBuilder(): Builder
    {
        $builder = parent::newBuilder();
        $pivotTable = $this->resolvePivotTable($this->pivotTable);
        $aliasPivot = 'p' . ++Orm::$alias;

        try {
            // Define join
            $builder->innerJoin(
                $pivotTable->getFullName(true),
                array_combine(
                    array_map(
                        fn($value) => $pivotTable->getColumn($value)->getName(true, $aliasPivot),
                        $this->pivotSourceColumns
                    ),
                    array_map(
                        fn($value) => $this->targetEntity
                            ->getTable()
                            ->getColumn($value)
                            ->getName(true, Builder::FROM_ALIAS),
                        $this->targetColumns
                    ),
                ),
                $aliasPivot
            );

            // Add pivot columns
            foreach ($this->getPivotTargetColumns() as $pivotColumnName) {
                $pivotColumn = $pivotTable->getColumn($pivotColumnName);

                $builder->withPivotColumn(
                    $pivotColumn->getName(true, $aliasPivot),
                    PivotData::PIVOT_KEY_PREFIX . $pivotColumn->getName()
                );
            }
        } catch (SchemaException $exception) {
            throw new OrmException(
                sprintf(
                    'Unable to resolve schema to add join for relationship "%s" of entity "%s"',
                    $this->name,
                    $this->getSourceEntity()
                ),
                previous: $exception
            );
        }

        return $builder;
    }

    /**
     * @inheritDoc
     */
    public function addJoinToBuilder(Builder $builder, string $alias, ?string $initialAlias = null): string
    {
        $sourceTable = $this->sourceEntity->getTable();
        $pivotTable = $this->resolvePivotTable($this->pivotTable);
        $targetTable = $this->targetEntity->getTable();
        $tableName = $targetTable->getFullName(true);

        if (false === $builder->join->hasAlias($alias)) {
            $aliasPivot = 'pivot' . ++Orm::$alias;

            try {
                $builder->leftJoin(
                    $pivotTable->getFullName(true),
                    array_combine(
                        array_map(
                            fn($value) => $sourceTable->getColumn($value)->getName(true, $initialAlias),
                            $this->getSourceColumns()
                        ),
                        array_map(
                            fn($value) => $pivotTable->getColumn($value)->getName(true, $aliasPivot),
                            $this->getPivotTargetColumns()
                        ),
                    ),
                    $aliasPivot
                )->leftJoin(
                    $tableName,
                    array_combine(
                        array_map(
                            fn($value) => $pivotTable->getColumn($value)->getName(true, $aliasPivot),
                            $this->getPivotSourceColumns()
                        ),
                        array_map(
                            fn($value) => $targetTable->getColumn($value)->getName(true, $alias),
                            $this->getTargetColumns()
                        ),
                    ),
                    $alias
                );
            } catch (SchemaException $exception) {
                throw new OrmException(
                    sprintf(
                        'Unable to resolve schema to add join for relationship "%s" of entity "%s"',
                        $this->name,
                        $this->getSourceEntity()
                    ),
                    previous: $exception
                );
            }
        }

        return $alias;
    }

    /**
     * @inheritDoc
     */
    public function getBuilder(Entity ...$entities): Builder
    {
        $entities = $this->filterEntities(...$entities);

        if (empty($entities)) {
            return $this->newBuilder();
        }

        return $this->newBuilder()->whereIn(
            new Row(...$this->getPivotTargetColumns()),
            $this->getEntityValues($this->sourceEntity, $this->getSourceColumns(), ...$entities)
        );
    }

    /**
     * @inheritDoc
     */
    public function get(Entity ...$entities): Collection
    {
        if (empty($this->filterEntities(...$entities))) {
            array_walk(
                $entities,
                fn(Entity $entity) => $entity->getRelated()->set(
                    $this->name,
                    new Collection()
                )
            );

            return new Collection();
        }

        // Set default collections
        array_walk(
            $entities,
            fn(Entity $entity) => $entity->getRelated()->set(
                $this->name,
                new Collection([], $this->targetEntity->getName())
            )
        );

        $foreigners = $this->getBuilder(...$entities)->yield();
        $entities = $this->tidyEntities($this->sourceColumns, ...$entities);
        $foreignersCollection = new Collection();

        /** @var Entity $foreign */
        foreach ($foreigners as $foreign) {
            $foreignReflection = ReflectionEntity::get($foreign::class);
            $pivot = $foreignReflection->getHectorData($foreign)->getPivot();

            // Not a pivot
            if (null === $pivot) {
                continue;
            }

            $pivotKeys = array_values($pivot->getKeys());

            foreach ($entities as $entity) {
                /** @var Related $entityRelated */
                $entityRelated = $entity['entity']->getRelated();

                if ($entity['columns'] != $pivotKeys) {
                    continue;
                }

                $entityRelated->get($this->name)->append($foreign);
                $foreignersCollection->append($foreign);
            }
        }

        return $foreignersCollection;
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function linkNative(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (!$foreign instanceof Collection) {
            throw new RelationException('Foreign must be a collection');
        }

        // Save foreign entity
        $foreign->save();

        // Link to entity if foreign is loaded
        if ($entity->getRelated()->isset($this->getName())) {
            $collection = $entity->getRelated()->get($this->getName());

            foreach ($foreign as $foreignEntity) {
                $collection[] = $foreignEntity;
            }
        }

        // Check if it has new relation
        /** @var Entity $foreignEntity */
        foreach ($foreign as $foreignEntity) {
            // Pivot
            $queryBuilder = $this->newQueryBuilder($entity, $foreignEntity);

            if (!$queryBuilder->exists()) {
                if ($queryBuilder->insert($foreignEntity->getPivot()?->getData() ?? []) !== 1) {
                    throw new RelationException(
                        sprintf(
                            'Error during creation of link between entities "%s" and "%s"',
                            $entity::class,
                            $foreignEntity::class
                        )
                    );
                }
                continue;
            }

            $queryBuilder->update($foreignEntity->getPivot()?->getData() ?? []);
        }

        // Detached
        foreach ($foreign->detached() as $detachedEntity) {
            $queryBuilder = $this->newQueryBuilder($entity, $detachedEntity);
            $queryBuilder->delete();
        }
        $foreign->clearDetached();
    }

    /**
     * New query builder.
     *
     * @param Entity $entity
     * @param Entity $foreign
     *
     * @return QueryBuilder
     * @throws OrmException
     */
    private function newQueryBuilder(Entity $entity, Entity $foreign): QueryBuilder
    {
        $queryBuilder = new QueryBuilder(Orm::get()->getConnection($this->sourceEntity->connection));
        $queryBuilder->from($this->pivotTable);

        $sourceValues = $this->getEntityValues($this->sourceEntity, $this->getSourceColumns(), $entity);
        $sourceValues = reset($sourceValues);
        $targetValues = $this->getEntityValues($this->targetEntity, $this->getTargetColumns(), $foreign);
        $targetValues = reset($targetValues);

        $queryBuilder->where(
            new Row(...array_merge($this->getPivotTargetColumns(), $this->getPivotSourceColumns())),
            '=',
            ($sourceValues + $targetValues)
        );
        $queryBuilder->assigns(array_combine($this->getPivotTargetColumns(), $sourceValues));
        $queryBuilder->assigns(array_combine($this->getPivotSourceColumns(), $targetValues));

        return $queryBuilder;
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        return new ManyToMany(
            $name,
            $this->getTargetEntity(),
            $this->getSourceEntity(),
            $this->pivotTable,
            array_combine($this->getTargetColumns(), $this->getPivotSourceColumns()),
            array_combine($this->getPivotTargetColumns(), $this->getSourceColumns())
        );
    }
}