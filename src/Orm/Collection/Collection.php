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

namespace Hector\Orm\Collection;

use ArrayObject;
use Generator;
use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Class Collection.
 */
class Collection extends ArrayObject implements JsonSerializable
{
    use EntityAssert;

    private string $accepted;

    /**
     * Collection constructor.
     *
     * @param iterable $input
     * @param string $accepted
     */
    public function __construct(iterable $input = [], string $accepted = Entity::class)
    {
        $this->assertEntity($accepted);

        $this->accepted = $accepted;

        parent::__construct($this->validInput($input));
    }

    /**
     * Get accepted entity.
     *
     * @return string
     */
    public function getAcceptedEntity(): string
    {
        return $this->accepted;
    }

    /**
     * Get iterator.
     *
     * @return FilterCollectionIterator
     */
    public function getIterator(): FilterCollectionIterator
    {
        return new FilterCollectionIterator(parent::getIterator(), fn($entity) => is_a($entity, $this->accepted, true));
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }

    /**
     * Filter collection.
     *
     * @param callable $callback
     *
     * @return Generator<Entity>
     */
    public function filter(callable $callback): Generator
    {
        yield from new FilterCollectionIterator(parent::getIterator(), $callback);
    }

    /**
     * Search entity.
     *
     * @param callable $callback
     *
     * @return Entity|null
     */
    public function search(callable $callback): ?Entity
    {
        $iterator = $this->filter($callback);

        return $iterator->current() ?: null;
    }

    /**
     * Map collection.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function map(callable $callback): void
    {
        foreach ($this as $entity) {
            $callback($entity);
        }
    }

    /**
     * Get first entity in collection.
     *
     * @return Entity|null
     */
    public function first(): ?Entity
    {
        $iterator = $this->getIterator();
        $iterator->rewind();

        return $iterator->current() ?: null;
    }

    /**
     * Save entities.
     *
     * @throws OrmException
     */
    public function save(): void
    {
        /** @var Entity $entity */
        foreach ($this as $entity) {
            $entity->save();
        }
    }

    /**
     * Delete entities.
     *
     * @throws OrmException
     */
    public function delete(): void
    {
        $keys = [];

        /** @var Entity $entity */
        foreach ($this as $key => $entity) {
            $keys[] = $key;

            if (!Orm::get()->isAttached($entity)) {
                continue;
            }

            $entity->delete();
        }

        foreach ($keys as $key) {
            unset($this[$key]);
        }
    }

    /**
     * Refresh entities.
     *
     * @throws OrmException
     */
    public function refresh(): void
    {
        /** @var Entity $entity */
        foreach ($this as $entity) {
            if (!Orm::get()->isAttached($entity)) {
                continue;
            }

            $entity->refresh();
        }
    }

    /**
     * Edge loading of relations.
     *
     * @param array $relations
     *
     * @return static
     * @throws OrmException
     */
    public function load(array $relations): static
    {
        // Get mapper for entity
        $acceptedEntityReflection = new ReflectionEntity($this->accepted);

        foreach ($relations as $key => $value) {
            $relationName = $value;
            if (is_array($value)) {
                $relationName = $key;
            }

            $related = $acceptedEntityReflection->getMapper()->getRelationships()->get($relationName)->get(...$this);

            if (is_array($value)) {
                $related->load($value);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (!is_a($value, $this->accepted, true)) {
            throw new InvalidArgumentException(sprintf('Value must be a "%s" class', $this->accepted));
        }

        if ($this->contains($value)) {
            return;
        }

        parent::offsetSet($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function exchangeArray(mixed $array): array
    {
        return parent::exchangeArray($this->validInput($array));
    }

    /**
     * Is in?
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function contains(Entity $entity): bool
    {
        if (!is_a($entity, $this->getAcceptedEntity(), true)) {
            return false;
        }

        /** @var Entity $entityInCollection */
        foreach ($this as $entityInCollection) {
            if ($entityInCollection === $entity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valid input.
     *
     * @param iterable $input
     *
     * @return iterable
     * @throws InvalidArgumentException
     */
    private function validInput(iterable $input): iterable
    {
        foreach ($input as $value) {
            if (!is_a($value, $this->accepted, true)) {
                throw new InvalidArgumentException(sprintf('Value must be an array of "%s" class', $this->accepted));
            }
        }

        return $input;
    }
}