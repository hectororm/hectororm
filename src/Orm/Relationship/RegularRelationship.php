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
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Query\Statement\Row;
use Hector\Schema\Exception\SchemaException;

abstract class RegularRelationship extends Relationship
{
    /**
     * AbstractRegularRelationship constructor.
     *
     * @param string $name
     * @param string $sourceEntity
     * @param string $targetEntity
     * @param array|null $columns
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        ?array $columns = null
    ) {
        parent::__construct($name, $sourceEntity, $targetEntity);

        if (!empty($columns)) {
            $this->sourceColumns = array_keys($columns);
            $this->targetColumns = array_values($columns);
        }
    }

    /**
     * @inheritDoc
     */
    public function getBuilder(Entity ...$entities): Builder
    {
        if (empty($entities)) {
            return $this->newBuilder();
        }

        return $this->newBuilder()->whereIn(
            new Row(...$this->targetColumns),
            $this->getEntityValues($this->sourceEntity, $this->sourceColumns, ...$entities)
        );
    }

    /**
     * @inheritDoc
     */
    public function get(Entity ...$entities): Collection
    {
        $foreigners = new Collection();

        if (!empty($filteredEntities = $this->filterEntities(...$entities))) {
            $entityValues = $this->getEntityValues($this->sourceEntity, $this->sourceColumns, ...$entities);

            if (!empty($entityValues)) {
                $foreigners = $this->getBuilder(...$filteredEntities)->all();
            }
        }

        $this->switchIntoEntities($foreigners, ...$entities);

        return $foreigners;
    }

    /**
     * @inheritDoc
     * @throws SchemaException
     */
    public function addJoinToBuilder(Builder $builder, ?string $initialAlias = null): string
    {
        $sourceTable = $this->sourceEntity->getTable();
        $targetTable = $this->targetEntity->getTable();

        $alias = 'a' . ++Orm::$alias;
        $builder->innerJoin(
            $targetTable->getFullName(true),
            array_combine(
                array_map(
                    fn($value) => $sourceTable->getColumn($value)->getName(true, $initialAlias),
                    $this->getSourceColumns()
                ),
                array_map(
                    fn($value) => $targetTable->getColumn($value)->getName(true, $alias),
                    $this->getTargetColumns()
                ),
            ),
            $alias
        );

        return $alias;
    }

    /**
     * Switch into entities.
     *
     * @param Collection $foreigners
     * @param Entity ...$entities
     *
     * @throws OrmException
     */
    abstract protected function switchIntoEntities(Collection $foreigners, Entity ...$entities);

//    /**
//     * Is attached entities?
//     *
//     * @param Entity $entity
//     * @param Entity $foreign
//     *
//     * @return bool
//     * @throws OrmException
//     */
//    protected function isAttached(Entity $entity, Entity $foreign)
//    {
//        if (!is_a($entity, $this->sourceEntity, true)) {
//            return false;
//        }
//        if (!is_a($foreign, $this->targetEntity, true)) {
//            return false;
//        }
//
//        $sourceValues = $this->sourceEntity::getMapper()->collectEntity($entity, $this->getSourceColumns());
//        $foreignValues = $foreign::getMapper()->collectEntity($foreign, $this->getTargetColumns());
//
//        // Keep only values, because column name can be different
//        $sourceValues = array_values($sourceValues);
//        $foreignValues = array_values($foreignValues);
//
//        return $sourceValues == $foreignValues;
//    }
}