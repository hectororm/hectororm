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

use Countable;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\Relationships;
use InvalidArgumentException;

class Related implements Countable
{
    private Entity $entity;
    private array $related = [];

    /**
     * Related constructor.
     *
     * @param Entity $entity
     */
    public function __construct(Entity $entity)
    {
        $this->entity = $entity;
    }

    public function __serialize(): array
    {
        return [
            'related' => $this->related,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->related = $data['related'];
    }

    /**
     * PHP magic method.
     *
     * @return array|null
     */
    public function __debugInfo(): ?array
    {
        return $this->related;
    }

    public function restore(Entity $entity): void
    {
        $this->entity = $entity;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->related);
    }

    /**
     * Get relationships.
     *
     * @return Relationships
     * @throws OrmException
     */
    private function getRelationships(): Relationships
    {
        return Orm::get()->getMapper($this->entity)->getRelationships();
    }

    /**
     * Get builder.
     *
     * @param string $name
     *
     * @return Builder
     * @throws OrmException
     */
    public function getBuilder(string $name): Builder
    {
        return $this->getRelationships()->get($name)->getBuilder($this->entity);
    }

    /**
     * Get related.
     *
     * @param string $name
     *
     * @return Collection<Entity>|Entity|null
     * @throws OrmException
     */
    public function get(string $name): Collection|Entity|null
    {
        if ($this->isset($name)) {
            return $this->related[$name];
        }

        // Get related
        $related = $this->getRelationships()->get($name);
        $related->get($this->entity);

        return $this->related[$name];
    }

    /**
     * __get() PHP magic method.
     *
     * @param string $name
     *
     * @return Collection<Entity>|Entity|null
     * @throws OrmException
     * @see Related::get()
     */
    public function __get(string $name): Collection|Entity|null
    {
        return $this->get($name);
    }

    /**
     * Set related.
     *
     * @param string $name
     * @param Entity|Collection|null $value
     *
     * @throws InvalidArgumentException
     * @throws OrmException
     * @todo Hydrate source entity if necessary
     */
    public function set(string $name, Collection|Entity|null $value): void
    {
        $relationship = $this->getRelationships()->get($name);

        if (false === $relationship->valid($value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid value for related property "%s", excepted "%s" entity',
                    $name,
                    $this->getRelationships()->get($name)->getTargetEntity()
                )
            );
        }

        $this->related[$name] = $value;
    }

    /**
     * __set() PHP magic method.
     *
     * @param string $name
     * @param Entity|Collection|null $value
     *
     * @throws OrmException
     * @see Related::set()
     */
    public function __set(string $name, Collection|Entity|null $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Isset related?
     *
     * @param string $name
     *
     * @return bool
     */
    public function isset(string $name): bool
    {
        return array_key_exists($name, $this->related);
    }

    /**
     * __isset() PHP magic method.
     *
     * @param string $name
     *
     * @return bool
     * @see Related::isset()
     */
    public function __isset(string $name): bool
    {
        return $this->isset($name);
    }

    /**
     * Unset related.
     *
     * @param string $name
     */
    public function unset(string $name): void
    {
        if (!$this->isset($name)) {
            return;
        }

        unset($this->related[$name]);
    }

    /**
     * __unset() PHP magic method.
     *
     * @param string $name
     *
     * @see Related::unset()
     */
    public function __unset(string $name): void
    {
        $this->unset($name);
    }

    /**
     * Relation exists?
     *
     * @param string $name
     *
     * @return bool
     * @throws OrmException
     */
    public function exists(string $name): bool
    {
        return $this->getRelationships()->exists($name);
    }

    /**
     * Link foreign.
     *
     * Call before entity saving.
     *
     * @throws OrmException
     */
    public function linkForeign(): void
    {
        foreach ($this->related as $relationshipName => $value) {
            $this->getRelationships()->get($relationshipName)->linkForeign($this->entity, $value);
        }
    }

    /**
     * Link native.
     *
     * Call after entity saving.
     *
     * @throws OrmException
     */
    public function linkNative(): void
    {
        foreach ($this->related as $relationshipName => $value) {
            $this->getRelationships()->get($relationshipName)->linkNative($this->entity, $value);
        }
    }

    /**
     * Save all related.
     *
     * @throws OrmException
     */
    public function save(bool $cascade = false): void
    {
        /** @var Collection|Entity $value */
        foreach ($this->related as $value) {
            $value->save($cascade);
        }
    }
}