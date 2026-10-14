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
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Query\Builder;
use Hector\Query\Statement\Quoted;

class OneToOne extends ManyToOne
{
    private ?bool $resolvedIsParent;
    private ?bool $orphanRemoval = null;

    /**
     * An omitted direction is inferred only from matching FK metadata. Null
     * resolution preserves the historical ManyToOne behavior until v2.
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        ?array $columns = null,
        private ?bool $isParent = null,
        ?bool $orphanRemoval = null,
    ) {
        $source = ReflectionEntity::get($sourceEntity);
        $target = ReflectionEntity::get($targetEntity);
        $candidates = $this->findCandidates($source, $target);
        $candidates = array_filter(
            $candidates,
            fn(array $candidate): bool => null === $isParent || $candidate['isParent'] === $isParent,
        );

        if (empty($columns)) {
            $columns = $this->inferColumns($name, $source, $target, $candidates);
        }

        $matching = array_values(array_filter(
            $candidates,
            static function (array $candidate) use ($columns): bool {
                $mapping = $candidate['columns'];
                ksort($mapping);
                ksort($columns);

                return $mapping === $columns;
            },
        ));
        $this->resolvedIsParent = $isParent ?? (1 === count($matching) ? $matching[0]['isParent'] : null);

        parent::__construct($name, $sourceEntity, $targetEntity, $columns);
        $this->setOrphanRemoval($orphanRemoval);
    }

    /**
     * Resolved role: true for a parent, false for a child, null for legacy fallback.
     */
    public function isParent(): ?bool
    {
        return $this->resolvedIsParent;
    }

    /**
     * Return the original configuration, before schema inference.
     */
    public function getConfiguredIsParent(): ?bool
    {
        return $this->isParent;
    }

    /**
     * @inheritDoc
     */
    public function setOrphanRemoval(?bool $orphanRemoval): void
    {
        if (true !== $this->resolvedIsParent) {
            parent::setOrphanRemoval($orphanRemoval);

            return;
        }

        $this->orphanRemoval = $orphanRemoval ?? false;
    }

    /**
     * Child-side references have no orphan-removal policy.
     */
    public function getOrphanRemoval(): ?bool
    {
        return $this->orphanRemoval;
    }

    /**
     * @inheritDoc
     */
    public function hasLifecyclePolicy(): bool
    {
        return null !== $this->resolvedIsParent;
    }

    /**
     * @inheritDoc
     */
    public function tracksAssignments(): bool
    {
        return true === $this->resolvedIsParent;
    }

    /**
     * @inheritDoc
     */
    public function getChildren(Entity|Collection|null $related): iterable
    {
        return true === $this->resolvedIsParent && $related instanceof Entity ? [$related] : [];
    }

    /**
     * @inheritDoc
     */
    public function prepareLifecycle(Entity $entity, Entity|Collection|null $foreign): void
    {
        foreach ($entity->getRelated()->getAssignment($this->name)['removed'] ?? [] as $removed) {
            if ($foreign instanceof Entity && true === $this->isSameChild($removed, $foreign)) {
                continue;
            }

            Orm::get()->lifecycle()->cancelPendingInsert($removed);
        }
    }

    /**
     * @inheritDoc
     */
    public function linkForeign(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (true !== $this->resolvedIsParent) {
            parent::linkForeign($entity, $foreign);
        }
    }

    /**
     * @inheritDoc
     */
    public function linkNative(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (true !== $this->resolvedIsParent) {
            return;
        }

        if (false === $this->valid($foreign)) {
            throw new RelationException('Foreign must be a target entity or null');
        }

        $lifecycle = Orm::get()->lifecycle();
        $lifecycle->transaction($entity, function () use ($entity, $foreign, $lifecycle): void {
            $this->prepareLifecycle($entity, $foreign);
            $previous = $this->getPreviousChild($entity);
            if (
                $previous instanceof Entity
                && (null === $foreign || false === $this->isSameChild($previous, $foreign))
            ) {
                $lifecycle->removeChild($this, $entity, $previous, $this->orphanRemoval ?? false);
            }

            if ($foreign instanceof Entity) {
                if (false === $this->hasRelationValues($entity)) {
                    throw new RelationException('Cannot link a child without complete parent key values');
                }

                $lifecycle->linkChild($this, $entity, $foreign);
            }

            $entity->getRelated()->clearAssignment($this->name);
        });
    }

    /**
     * Resolve an unloaded baseline using this relation view, never an unfiltered
     * query that could delete a child hidden by where/having/limit clauses.
     */
    private function getPreviousChild(Entity $entity): ?Entity
    {
        $assignment = $entity->getRelated()->getAssignment($this->name);
        if (null === $assignment) {
            return null;
        }

        if (true === $assignment['loaded']) {
            return $assignment['previous'];
        }

        if (
            null === $this->sourceEntity->getHectorData($entity)->get('original')
            || false === $this->hasRelationValues($entity)
        ) {
            return null;
        }

        $previous = $this->getBuilder($entity)->all();
        if (count($previous) > 1) {
            throw new RelationException(sprintf('Relationship "%s" resolves to more than one child', $this->name));
        }

        return $previous->first();
    }

    /**
     * A new object with an assigned/shared primary key still requires an INSERT;
     * only persisted identities (or the same object) suppress replacement.
     */
    private function isSameChild(Entity $previous, Entity $current): bool
    {
        if ($previous === $current) {
            return true;
        }

        if (
            null === ReflectionEntity::get($previous)->getHectorData($previous)->get('original')
            || null === ReflectionEntity::get($current)->getHectorData($current)->get('original')
        ) {
            return false;
        }

        $previousKey = ReflectionEntity::get($previous)->getMapper()->getPrimaryValue($previous);
        $currentKey = ReflectionEntity::get($current)->getMapper()->getPrimaryValue($current);
        if (
            empty($previousKey) || empty($currentKey)
            || in_array(null, $previousKey, true) || in_array(null, $currentKey, true)
        ) {
            return false;
        }

        return self::keysMatch($previousKey, $currentKey);
    }

    /**
     * Use scalar equality for a single source, including composite mappings.
     */
    public function getBuilder(Entity ...$entities): Builder
    {
        if (null === $this->resolvedIsParent || 1 !== count($entities)) {
            return parent::getBuilder(...$entities);
        }

        $this->assertEntityType($entities[0], $this->getSourceEntity());

        if (false === $this->hasRelationValues($entities[0])) {
            return $this->newBuilder()->whereIn(new Quoted($this->getTargetColumns()[0]), []);
        }

        $values = $this->sourceEntity->getMapper()->collectEntity($entities[0], $this->getSourceColumns());

        return $this->newBuilder()->whereEquals(array_combine($this->getTargetColumns(), array_values($values)));
    }

    /**
     * A parent-side one-to-one relation must not silently select an arbitrary child.
     */
    protected function switchIntoEntities(Collection $foreigners, Entity ...$entities): void
    {
        if (true === $this->resolvedIsParent) {
            $sources = $this->tidyEntities($this->getSourceColumns(), ...$entities);
            $targets = $this->tidyEntities($this->getTargetColumns(), ...$foreigners);
            foreach ($sources as $source) {
                $matches = array_filter(
                    $targets,
                    static fn(array $target): bool => self::keysMatch($source['columns'], $target['columns']),
                );
                if (count($matches) > 1) {
                    throw new RelationException(sprintf(
                        'Relationship "%s" resolves to more than one child',
                        $this->name,
                    ));
                }
            }
        }

        parent::switchIntoEntities($foreigners, ...$entities);
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        $reverse = new OneToOne(
            $name,
            $this->getTargetEntity(),
            $this->getSourceEntity(),
            array_combine($this->getTargetColumns(), $this->getSourceColumns()),
            isParent: null === $this->resolvedIsParent ? null : !$this->resolvedIsParent,
        );

        // Preserve an unresolved historical relationship in both directions.
        if (null === $this->resolvedIsParent) {
            $reverse->resolvedIsParent = null;
            $reverse->orphanRemoval = null;
        }

        return $reverse;
    }

    /**
     * Gather exact source-to-target column pairs from both FK directions.
     * Compare table/schema names directly so unrelated unresolved FKs are harmless.
     */
    private function findCandidates(ReflectionEntity $source, ReflectionEntity $target): array
    {
        if ($source->connection !== $target->connection) {
            return [];
        }

        $candidates = [];
        foreach ([false, true] as $isParent) {
            $dependent = ($isParent ? $target : $source)->getTable();
            $principal = ($isParent ? $source : $target)->getTable();
            foreach ($dependent->getForeignKeys() as $foreignKey) {
                if (
                    $foreignKey->getReferencedTableName() !== $principal->getName()
                    || $foreignKey->getReferencedSchemaName() !== $principal->getSchemaName()
                ) {
                    continue;
                }

                $local = $foreignKey->getColumnsName();
                $referenced = $foreignKey->getReferencedColumnsName();
                if (empty($local) || count($local) !== count($referenced)) {
                    continue;
                }

                foreach ($local as $index => $column) {
                    $reference = $referenced[$index];
                    if (
                        false === $dependent->hasColumn($column)
                        || false === is_string($reference)
                        || '' === $reference
                        || false === $principal->hasColumn($reference)
                    ) {
                        continue 2;
                    }
                }

                $columns = $isParent ? array_combine($referenced, $local) : array_combine($local, $referenced);
                if (count($columns) !== count($local) || count(array_unique($columns)) !== count($columns)) {
                    continue;
                }

                ksort($columns);
                $candidate = ['isParent' => $isParent, 'columns' => $columns];
                if (false === in_array($candidate, $candidates, true)) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * Infer one mapping or require explicit columns when several associations exist.
     */
    private function inferColumns(
        string $name,
        ReflectionEntity $source,
        ReflectionEntity $target,
        array $candidates,
    ): array {
        $mappings = [];
        foreach ($candidates as $candidate) {
            if (false === in_array($candidate['columns'], $mappings, true)) {
                $mappings[] = $candidate['columns'];
            }
        }

        if (count($mappings) > 1) {
            throw new RelationException(sprintf(
                'Ambiguous columns for relationship "%s"; declare columns explicitly',
                $name,
            ));
        }

        if (1 === count($mappings)) {
            return $mappings[0];
        }

        $principal = true === $this->isParent ? $source : $target;
        $columns = $principal->getPrimaryIndex()?->getColumnsName();
        if (empty($columns)) {
            throw new RelationException(sprintf('Unable to deduct columns for relationship "%s"', $name));
        }

        return array_combine($columns, $columns);
    }
}
