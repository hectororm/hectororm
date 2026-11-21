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

use Hector\Orm\Assert\MorphAssert;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Query\Builder;
use Hector\Query\Component\Conditions;
use Hector\Query\Statement\Expression;
use Hector\Query\Statement\Quoted;
use Hector\Query\Statement\Row;

/**
 * Child side of a polymorphic relationship: the source row names its target type through a discriminator.
 */
class MorphTo extends Relationship
{
    use MorphAssert;

    /**
     * @var list<array{
     *     entity: ReflectionEntity,
     *     columns: list<string>,
     *     discriminator: array<string, bool|float|int|string>
     * }>
     */
    private array $types = [];

    /**
     * @param array<string, string> $columns Source column to target column
     * @param array<string, bool|float|int|string> $discriminator Source column to the value identifying the target
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        array $columns,
        array $discriminator,
    ) {
        parent::__construct($name, $sourceEntity, $targetEntity);

        $this->sourceColumns = array_keys($columns);
        $this->targetColumns = array_values($columns);
        $this->addType($targetEntity, $columns, $discriminator);
    }

    /**
     * Add a target type sharing the source columns of this relationship.
     *
     * @param array<string, string> $columns Source column to target column
     * @param array<string, bool|float|int|string> $discriminator Source column to the value identifying the target
     *
     * @throws OrmException
     */
    public function addType(string $targetEntity, array $columns, array $discriminator): static
    {
        $this->assertEntity($targetEntity);
        $target = ReflectionEntity::get($targetEntity);

        $this->assertMorphColumns($this->name, $columns);
        $this->assertDiscriminator($this->name, $discriminator, array_keys($columns));

        if ([] !== $this->types) {
            $columns = self::alignColumns($this->sourceColumns, $columns)
                ?? throw new RelationException(sprintf(
                    'Targets of polymorphic relationship "%s" must share the same source columns',
                    $this->name,
                ));
            $discriminator = self::alignColumns($this->getDiscriminatorColumns(), $discriminator)
                ?? throw new RelationException(sprintf(
                    'Targets of polymorphic relationship "%s" must share the same discriminator columns',
                    $this->name,
                ));
        }

        foreach ($this->types as $type) {
            if ($type['entity']->getName() === $target->getName()) {
                throw new RelationException(sprintf(
                    'Polymorphic relationship "%s" already targets "%s"',
                    $this->name,
                    $target->getName(),
                ));
            }

            if (true === self::keysMatch($type['discriminator'], $discriminator)) {
                throw new RelationException(sprintf(
                    'Targets "%s" and "%s" of polymorphic relationship "%s" share the same discriminator',
                    $type['entity']->getName(),
                    $target->getName(),
                    $this->name,
                ));
            }
        }

        $this->types[] = [
            'entity' => $target,
            'columns' => array_values($columns),
            'discriminator' => $discriminator,
        ];

        return $this;
    }

    /**
     * @inheritDoc
     * @throws RelationException When the relationship has several targets
     */
    public function getTargetEntity(): string
    {
        $this->assertSingleTarget();

        return parent::getTargetEntity();
    }

    /**
     * Get target entities.
     *
     * @return list<class-string<Entity>>
     */
    public function getTargetEntities(): array
    {
        return array_map(static fn(array $type): string => $type['entity']->getName(), $this->types);
    }

    /**
     * @inheritDoc
     * @throws RelationException When the relationship has several targets
     */
    public function getTargetColumns(): array
    {
        $this->assertSingleTarget();

        return parent::getTargetColumns();
    }

    /**
     * Get the source columns naming the target type.
     *
     * @return list<string>
     */
    public function getDiscriminatorColumns(): array
    {
        return array_keys($this->types[0]['discriminator']);
    }

    /**
     * Only an explicit assignment may write the columns: they can be shared with other polymorphic relationships.
     */
    public function tracksAssignments(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function referencesParentOf(Relationship $parentRelationship): bool
    {
        return $this->getSourceColumns() === $parentRelationship->getTargetColumns();
    }

    /**
     * @inheritDoc
     */
    public function getBuilder(Entity ...$entities): Builder
    {
        $index = null;
        $sources = [];

        foreach ($entities as $entity) {
            $this->assertEntityType($entity, $this->getSourceEntity());

            if (null === ($entityIndex = $this->resolveType($entity))) {
                continue;
            }

            if (null !== $index && $index !== $entityIndex) {
                throw new RelationException(sprintf(
                    'Polymorphic relationship "%s" cannot query several target entities at once',
                    $this->name,
                ));
            }

            $index = $entityIndex;
            $sources[] = $entity;
        }

        if (null === $index) {
            $this->assertSingleTarget();
            $index = 0;
        }

        $type = $this->types[$index];

        return $this->newTypeBuilder($type['entity'])->whereIn(
            new Row(...$type['columns']),
            $this->getEntityValues($this->sourceEntity, $this->sourceColumns, ...$sources),
        );
    }

    /**
     * @inheritDoc
     */
    public function addJoinToBuilder(
        Builder $builder,
        string $alias,
        ?string $initialAlias = null,
    ): string {
        // The table to join depends on each row: only a single target can be expressed as a join.
        if (1 !== count($this->types)) {
            throw new RelationException(sprintf(
                'Polymorphic relationship "%s" has several target entities and cannot be joined',
                $this->name,
            ));
        }

        if (true === $builder->join->hasAlias($alias)) {
            return $alias;
        }

        $type = $this->types[0];
        $sourceTable = $this->sourceEntity->getTable();
        $targetTable = $type['entity']->getTable();

        $conditions = array_map(
            fn(string $src, string $tgt): Expression => new Expression(
                new Quoted($sourceTable->getColumn($src)->getName(tableAlias: $initialAlias)),
                ' = ',
                new Quoted($targetTable->getColumn($tgt)->getName(tableAlias: $alias)),
            ),
            $this->sourceColumns,
            $type['columns'],
        );

        $morphConditions = new Conditions();
        foreach ($type['discriminator'] as $column => $value) {
            $morphConditions->equal(
                new Quoted($sourceTable->getColumn($column)->getName(tableAlias: $initialAlias)),
                $value,
            );
        }

        $conditions[] = $morphConditions;

        $builder->leftJoin(new Quoted($targetTable->getFullName()), $conditions, $alias);

        return $alias;
    }

    /**
     * Whether this relationship writes columns also written by another polymorphic reference.
     *
     * @internal
     */
    public function sharesColumnsWith(MorphTo $relationship): bool
    {
        return [] !== array_intersect(
            [...$this->sourceColumns, ...$this->getDiscriminatorColumns()],
            [...$relationship->sourceColumns, ...$relationship->getDiscriminatorColumns()],
        );
    }

    /**
     * @inheritDoc
     */
    public function get(Entity ...$entities): Collection
    {
        $groups = [];
        foreach ($this->filterEntities(...$entities) as $entity) {
            if (null !== ($index = $this->resolveType($entity))) {
                $groups[$index][] = $entity;
            }
        }

        $foreigners = [];
        $matches = [];
        foreach ($groups as $index => $sources) {
            $type = $this->types[$index];
            $values = $this->getEntityValues($this->sourceEntity, $this->sourceColumns, ...$sources);
            if ([] === $values) {
                continue;
            }

            $found = array_values(
                $this->newTypeBuilder($type['entity'])
                    ->whereIn(new Row(...$type['columns']), $values)
                    ->all()
                    ->getArrayCopy(),
            );
            $targets = $this->tidyEntities($type['columns'], ...$found);

            foreach ($this->tidyEntities($this->sourceColumns, ...$sources) as $source) {
                foreach ($targets as $target) {
                    if (true === self::keysMatch($target['columns'], $source['columns'])) {
                        $matches[spl_object_id($source['entity'])] = $target['entity'];
                        break;
                    }
                }
            }

            array_push($foreigners, ...$found);
        }

        // A row of another type, or without a match, resolves to null: it never borrows a target by key alone.
        foreach ($entities as $entity) {
            $entity->getRelated()->setLoaded($this->name, $matches[spl_object_id($entity)] ?? null);
        }

        return new Collection($foreigners);
    }

    /**
     * @inheritDoc
     * @throws OrmException
     */
    public function linkForeign(Entity $entity, Entity|Collection|null $foreign): void
    {
        if (null !== $foreign && !$foreign instanceof Entity) {
            throw new RelationException('Foreign must be an entity');
        }

        // A loaded value is already consistent with the columns it was read from.
        if (null === $entity->getRelated()->getAssignment($this->name)) {
            return;
        }

        $mapper = ReflectionEntity::get($entity::class)->getMapper();

        if (null === $foreign) {
            // The key of a row belonging to another relationship is not this relationship's to clear.
            if (null !== $this->resolveType($entity)) {
                $mapper->hydrateEntity($entity, array_fill_keys($this->sourceColumns, null));
            }

            return;
        }

        if (null === ($index = $this->findType($foreign))) {
            throw new RelationException(sprintf(
                'Entity "%s" is not a target of polymorphic relationship "%s"',
                $foreign::class,
                $this->name,
            ));
        }

        $type = $this->types[$index];

        // Save foreign entity to get collect columns ; ONLY IF ALTERED!
        if ($foreign->isAltered(...$type['columns'])) {
            $foreign->save();
        }

        $values = ReflectionEntity::get($foreign::class)->getMapper()->collectEntity($foreign, $type['columns']);

        $mapper->hydrateEntity(
            $entity,
            array_combine($this->sourceColumns, array_values($values)) + $type['discriminator'],
        );
    }

    /**
     * @inheritDoc
     */
    public function linkNative(Entity $entity, Entity|Collection|null $foreign): void
    {
        $entity->getRelated()->clearAssignment($this->name);
    }

    /**
     * @inheritDoc
     */
    public function valid(mixed &$related): bool
    {
        if (null === $related) {
            return true;
        }

        return $related instanceof Entity && null !== $this->findType($related);
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        throw new RelationException(sprintf(
            'Polymorphic relationship "%s.%s" cannot be inverted as "%s"; declare MorphMany or MorphOne instead',
            $this->getSourceEntity(),
            $this->name,
            $name,
        ));
    }

    /**
     * @throws RelationException
     */
    private function assertSingleTarget(): void
    {
        if (1 === count($this->types)) {
            return;
        }

        throw new RelationException(sprintf(
            'Polymorphic relationship "%s" has several target entities',
            $this->name,
        ));
    }

    /**
     * Resolve the target type named by the discriminator columns of a source entity.
     *
     * @throws OrmException
     */
    private function resolveType(Entity $entity): ?int
    {
        $values = ReflectionEntity::get($entity::class)->getMapper()->collectEntity(
            $entity,
            $this->getDiscriminatorColumns(),
        );

        foreach ($this->types as $index => $type) {
            if (true === self::keysMatch($type['discriminator'], $values)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Find the target type of a foreign entity, preferring its exact class over a parent class.
     */
    private function findType(Entity $foreign): ?int
    {
        $inherited = null;

        foreach ($this->types as $index => $type) {
            if ($foreign::class === $type['entity']->getName()) {
                return $index;
            }

            if (null === $inherited && $foreign instanceof ($type['entity']->getName())) {
                $inherited = $index;
            }
        }

        return $inherited;
    }

    /**
     * New builder on a target, with the clauses of this relationship.
     */
    private function newTypeBuilder(ReflectionEntity $target): Builder
    {
        $builder = $target->getName()::query();
        $builder->where = clone $this->where;
        $builder->group = clone $this->group;
        $builder->having = clone $this->having;
        $builder->order = clone $this->order;
        $builder->limit = clone $this->limit;

        return $builder;
    }

    /**
     * Reorder a column map after reference columns, or return null when the columns differ.
     */
    private static function alignColumns(array $reference, array $values): ?array
    {
        if (count($reference) !== count($values)) {
            return null;
        }

        $aligned = [];
        foreach ($reference as $column) {
            if (false === array_key_exists($column, $values)) {
                return null;
            }

            $aligned[$column] = $values[$column];
        }

        return $aligned;
    }
}
