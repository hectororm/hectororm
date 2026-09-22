<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Storage\EntityStorage;
use Hector\Orm\Storage\LifecycleTransaction;
use LogicException;
use SplObjectStorage;

/**
 * Orchestrates relationship lifecycle policies and their transactional state.
 */
final class Lifecycle
{
    /** @var LifecycleTransaction|null Active lifecycle transaction, shared by nested operations. */
    private ?LifecycleTransaction $transaction = null;

    /**
     * @param Orm $orm
     * @param EntityStorage $storage
     */
    public function __construct(private Orm $orm, private EntityStorage $storage)
    {
    }

    /**
     * Whether a lifecycle transaction is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return null !== $this->transaction;
    }

    /**
     * Execute a lifecycle operation atomically on the entity connection.
     * Nested calls share the active context and its rollback snapshots.
     *
     * @param Entity $entity
     * @param callable(): mixed $operation
     * @return mixed
     */
    public function transaction(Entity $entity, callable $operation): mixed
    {
        if (null !== $this->transaction) {
            $this->transaction->capture($entity);

            return $operation();
        }

        $transaction = new LifecycleTransaction(
            $this->orm,
            $this->storage,
            $this->orm->getConnection(ReflectionEntity::get($entity)->connection),
        );
        $this->transaction = $transaction;

        try {
            $transaction->capture($entity);

            return $transaction->run($operation);
        } finally {
            $this->transaction = null;
        }
    }

    /**
     * Capture state before relationship key propagation or removal.
     *
     * @internal
     */
    public function track(Entity|Collection $value): void
    {
        if ($value instanceof Collection) {
            $this->transaction?->captureCollection($value);

            return;
        }

        $this->transaction?->capture($value);
    }

    /**
     * Flush pending entities inside the active lifecycle transaction.
     * Capture the entire batch before cancelling removed transient children so
     * rollback restores their pending status regardless of scheduling order.
     *
     * @param callable(Entity): void $persistEntity
     * @internal
     */
    public function persistBatch(callable $persistEntity): void
    {
        if (false === $this->isActive()) {
            throw new LogicException('Lifecycle batch persistence requires an active transaction');
        }

        $pending = [];
        foreach ($this->storage as $entity) {
            if (EntityStorage::STATUS_NONE === $this->orm->getStatus($entity)) {
                continue;
            }

            $this->track($entity);
            $pending[] = $entity;
        }

        $visited = new SplObjectStorage();
        foreach ($pending as $entity) {
            $entity->getRelated()->prepareLifecycle($visited);
        }

        foreach ($pending as $entity) {
            $persistEntity($entity);
        }
    }

    /**
     * Cancel a removed child insert that has not reached the database.
     *
     * @internal
     */
    public function cancelPendingInsert(Entity $entity): void
    {
        $this->track($entity);

        if (
            EntityStorage::STATUS_TO_INSERT === $this->orm->getStatus($entity)
            && null === ReflectionEntity::get($entity)->getHectorData($entity)->get('original')
        ) {
            $this->storage->detach($entity);
        }
    }

    /**
     * Apply the removal policy to an explicitly removed child.
     * Call within transaction() to include attachment of a replacement.
     *
     * @internal
     */
    public function removeChild(
        Relationship $relationship,
        Entity $parent,
        Entity $child,
        bool $orphanRemoval,
    ): void {
        $this->track($child);
        $reflection = ReflectionEntity::get($child);
        $original = $reflection->getHectorData($child)->get('original');

        // Transient children have nothing to detach/delete in the database.
        if (null === $original) {
            $this->cancelPendingInsert($child);

            return;
        }

        $this->assertParentLink($relationship, $parent, $original);

        if (true === $orphanRemoval) {
            $child->delete();
            if (null !== $this->orm->getStatus($child)) {
                throw new RelationException('Child removal was prevented; the lifecycle operation was rolled back');
            }

            return;
        }

        $this->detachChild($relationship, $child);
    }

    /**
     * Validate the original linking values before removing a persisted child.
     */
    private function assertParentLink(Relationship $relationship, Entity $parent, array $original): void
    {
        $parentValues = array_values(
            ReflectionEntity::get($parent)->getMapper()->collectEntity($parent, $relationship->getSourceColumns()),
        );

        foreach (array_values($relationship->getTargetColumns()) as $index => $column) {
            $expected = $parentValues[$index];
            if (
                null === $expected || false === array_key_exists($column, $original)
                || (string)$original[$column] !== (string)$expected
            ) {
                throw new RelationException('Cannot remove a child without its original parent relationship');
            }
        }
    }

    /**
     * Clear nullable child linking columns without deleting the entity.
     */
    private function detachChild(Relationship $relationship, Entity $child): void
    {
        $reflection = ReflectionEntity::get($child);

        foreach ($relationship->getTargetColumns() as $column) {
            if (
                false === $reflection->getTable()->getColumn($column)->isNullable()
                || in_array($column, $reflection->getPrimaryIndex()?->getColumnsName() ?? [], true)
            ) {
                throw new RelationException(sprintf(
                    'Cannot detach child through "%s": column "%s" cannot be cleared; use orphanRemoval: true',
                    $relationship->getName(),
                    $column,
                ));
            }
        }

        // A cached inverse reference would otherwise put the parent key back in
        // ManyToOne::linkForeign(). Invalidate matching local parent references.
        $child->getRelated()->invalidateParent($relationship);
        $reflection->getMapper()->hydrateEntity($child, array_fill_keys($relationship->getTargetColumns(), null));
        $child->save();
        if (
            EntityStorage::STATUS_NONE !== $this->orm->getStatus($child)
            || $child->isAltered(...$relationship->getTargetColumns())
            || [] !== array_filter(
                $reflection->getMapper()->collectEntity($child, $relationship->getTargetColumns()),
                fn($value): bool => null !== $value,
            )
        ) {
            throw new RelationException('Child detachment was prevented; the lifecycle operation was rolled back');
        }
    }
}
