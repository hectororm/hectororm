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

namespace Hector\Orm\Relationship;

use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Storage\EntityStorage;

/**
 * Shared removal operation for parent-side scalar and collection relationships.
 * The caller identifies explicitly removed children and owns the transaction.
 *
 * @internal
 */
final class ChildLifecycle
{
    public static function remove(Relationship $relationship, Entity $parent, Entity $child, bool $delete): void
    {
        $orm = Orm::get();
        $orm->trackLifecycle($child);
        $reflection = ReflectionEntity::get($child);
        $mapper = $reflection->getMapper();
        $original = $reflection->getHectorData($child)->get('original');

        // Transient children have nothing to detach/delete in the database.
        if (null === $original) {
            $orm->cancelPendingInsert($child);
            return;
        }

        $parentValues = ReflectionEntity::get($parent)->getMapper()->collectEntity(
            $parent,
            $relationship->getSourceColumns(),
        );
        foreach (array_values($relationship->getTargetColumns()) as $index => $column) {
            $expected = array_values($parentValues)[$index];
            if (null === $expected || !array_key_exists($column, $original)
                || (string)$original[$column] !== (string)$expected) {
                throw new RelationException('Cannot remove a child without its original parent relationship');
            }
        }

        if ($delete) {
            $child->delete();
            if (null !== $orm->getStatus($child)) {
                throw new RelationException('Child removal was prevented; the lifecycle operation was rolled back');
            }
            return;
        }

        foreach ($relationship->getTargetColumns() as $column) {
            if (false === $reflection->getTable()->getColumn($column)->isNullable()
                || in_array($column, $reflection->getPrimaryIndex()?->getColumnsName() ?? [], true)) {
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
        $mapper->hydrateEntity($child, array_fill_keys($relationship->getTargetColumns(), null));
        $child->save();
        if (EntityStorage::STATUS_NONE !== $orm->getStatus($child)
            || $child->isAltered(...$relationship->getTargetColumns())) {
            throw new RelationException('Child detachment was prevented; the lifecycle operation was rolled back');
        }
    }
}
