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

use Hector\Orm\Collection\Collection;
use Hector\Orm\Collection\LazyCollection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Storage\EntityStorage;
use PDOException;

interface Mapper
{
    /**
     * Mapper constructor.
     *
     * @param string $entity
     * @param EntityStorage $storage
     *
     * @throws OrmException if an error occurred during creation of mapper
     */
    public function __construct(string $entity, EntityStorage $storage);

    /**
     * Get relationships.
     *
     * @return Relationships
     * @throws OrmException
     */
    public function getRelationships(): Relationships;

    /**
     * Get primary value of entity.
     *
     * @param Entity $entity
     *
     * @return array|null
     */
    public function getPrimaryValue(Entity $entity): ?array;

    /**
     * Fetch entity with Builder object.
     *
     * @param Builder $builder
     *
     * @return Entity|null
     * @throws OrmException
     */
    public function fetchOneWithBuilder(Builder $builder): ?Entity;

    /**
     * Fetch entities with Builder object.
     *
     * @param Builder $builder
     *
     * @return Collection<Entity>
     * @throws OrmException
     */
    public function fetchAllWithBuilder(Builder $builder): Collection;

    /**
     * Fetch entities with Builder object with Generator.
     *
     * @param Builder $builder
     *
     * @return LazyCollection<Entity>
     * @throws OrmException
     */
    public function yieldWithBuilder(Builder $builder): LazyCollection;

    /**
     * Update entity.
     *
     * @param Entity $entity
     *
     * @return int Number of affected rows
     * @throws OrmException
     */
    public function insertEntity(Entity $entity): int;

    /**
     * Update entity.
     *
     * @param Entity $entity
     *
     * @return int Number of affected rows
     * @throws OrmException
     */
    public function updateEntity(Entity $entity): int;

    /**
     * Delete entity.
     *
     * @param Entity $entity
     *
     * @return int Number of affected rows
     * @throws OrmException
     */
    public function deleteEntity(Entity $entity): int;

    /**
     * Refresh entity.
     *
     * @param Entity $entity
     *
     * @throws OrmException
     * @throws PDOException
     */
    public function refreshEntity(Entity $entity): void;

    /**
     * Hydrate entity with data.
     *
     * @param Entity $entity
     * @param array $data
     *
     * @throws OrmException
     */
    public function hydrateEntity(Entity $entity, array $data): void;

    /**
     * Collect data from entity.
     *
     * If columns parameter specified, MUST return ordered data like columns array.
     *
     * @param Entity $entity
     * @param array|null $columns
     *
     * @return array
     * @throws OrmException
     */
    public function collectEntity(Entity $entity, ?array $columns = null): array;

    /**
     * Get entity alteration.
     *
     * @param Entity $entity
     * @param array|null $columns
     *
     * @return array
     * @throws OrmException
     */
    public function getEntityAlteration(Entity $entity, ?array $columns = null): array;
}
