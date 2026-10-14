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

namespace Hector\Orm\Tests\Relationship;

use Attribute;
use Closure;
use Error;
use Hector\Connection\Connection;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Mapper\MagicMapper;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class RelationshipResolutionTest extends TestCase
{
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $this->previousReflections = $reflections->getValue();
        $reflections->setValue(null, []);

        $connection = new Connection('sqlite::memory:');
        $connection->execute('PRAGMA foreign_keys = ON');
        $connection->execute(
            'CREATE TABLE resolution_category (id INTEGER PRIMARY KEY, '
            . 'parent_id INTEGER REFERENCES resolution_category(id))',
        );
        $connection->execute('CREATE TABLE resolution_parent (id INTEGER PRIMARY KEY)');
        $connection->execute(
            'CREATE TABLE resolution_child (id INTEGER PRIMARY KEY, '
            . 'parent_id INTEGER REFERENCES resolution_parent(id))',
        );
        $this->orm = new Orm($connection, (new Sqlite($connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        ResolutionObservedHasMany::$onConstruct = null;
        ResolutionObservedHasMany::$onAdd = null;
        ResolutionObservedHasMany::$onClauses = null;
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    /** @dataProvider categoryProvider */
    public function testSelfReferenceResolvesInEitherAttributeOrder(string $entity): void
    {
        $relationships = $this->orm->getMapper($entity)->getRelationships();
        $children = $relationships->get('children');
        $parent = $relationships->get('parent');

        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertInstanceOf(ManyToOne::class, $parent);
        $this->assertSame(['id'], $children->getSourceColumns());
        $this->assertSame(['parent_id'], $children->getTargetColumns());
        $this->assertSame(['parent_id'], $parent->getSourceColumns());
        $this->assertSame(['id'], $parent->getTargetColumns());
        $this->assertSame($children, $relationships->get('children'));
        $this->assertCount(2, $relationships);
    }

    public function categoryProvider(): array
    {
        return [
            'concrete first' => [ResolutionConcreteFirstCategory::class],
            'inverse first' => [ResolutionInverseFirstCategory::class],
        ];
    }

    public function testDiscoveryDoesNotConstructRelationships(): void
    {
        $mapper = $this->orm->getMapper(ResolutionInvalidCategory::class);
        $relationships = $mapper->getRelationships();

        $this->assertSame($relationships, $mapper->getRelationships());
        $this->assertCount(2, $relationships);
        $this->assertTrue($relationships->exists('parent'));
        $this->assertTrue($relationships->exists('children'));
        $this->assertFalse($relationships->exists('missing'));
        $relationships->assertExists('children');

        $this->assertInstanceOf(ManyToOne::class, $relationships->get('parent'));
        $this->assertCount(2, $relationships);
    }

    public function testProgrammaticDeclarationReturnsConfigurableRelationshipImmediately(): void
    {
        $relationships = $this->orm->getMapper(ResolutionInvalidCategory::class)->getRelationships();
        $children = $relationships->hasMany(
            ResolutionInvalidCategory::class,
            'children',
            ['id' => 'parent_id'],
        )->orderBy('id', 'DESC');

        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertSame($children, $relationships->get('children'));
        $this->assertCount(1, $relationships->get('children')->order);
        $this->assertCount(2, $relationships);
    }

    public function testUnnamedLookupFindsUnconstructedDeclaration(): void
    {
        $relationships = $this->orm->getMapper(ResolutionParent::class)->getRelationships();
        $children = $relationships->getWith(ResolutionChild::class);

        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertSame($children, $relationships->get('children'));
        $this->assertSame(['parent_id'], $children->getTargetColumns());
    }

    public function testUnknownNameStillThrowsAfterDiscovery(): void
    {
        $relationships = $this->orm->getMapper(ResolutionInvalidCategory::class)->getRelationships();

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship "unknown" not found');
        $relationships->get('unknown');
    }

    public function testFailedInitializationDoesNotExposeProvisionalRelationshipOnRetry(): void
    {
        $mapper = $this->orm->getMapper(ResolutionInvalidCategory::class);
        $messages = [];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $mapper->getRelationships()->get('children');
                $this->fail('Invalid inverse relationship must fail on every access');
            } catch (RelationException $exception) {
                $messages[] = $exception->getMessage();
                $this->assertStringContainsString('missing', $exception->getMessage());
            }
        }

        $this->assertCount(2, $messages);
        $this->assertSame($messages[0], $messages[1]);
    }

    /** @dataProvider entryPointProvider */
    public function testCrossMapperResolutionDoesNotDependOnEntryPoint(string $entryPoint): void
    {
        $this->orm->getMapper($entryPoint)->getRelationships()->get('children');
        $parentRelations = $this->orm->getMapper(ResolutionParent::class)->getRelationships();
        $childRelations = $this->orm->getMapper(ResolutionChild::class)->getRelationships();

        $children = $parentRelations->get('children');
        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertSame(['id'], $children->getSourceColumns());
        $this->assertSame(['parent_id'], $children->getTargetColumns());
        $this->assertInstanceOf(ManyToOne::class, $childRelations->get('parent'));
        $this->assertInstanceOf(ManyToOne::class, $childRelations->get('children'));
    }

    public function entryPointProvider(): array
    {
        return [
            'parent first' => [ResolutionParent::class],
            'child first' => [ResolutionChild::class],
        ];
    }

    /** @dataProvider chainEntryPointProvider */
    public function testChainOfInversionsEndsAtConcreteRelationship(string $entity, string $name): void
    {
        $relationship = $this->orm->getMapper($entity)->getRelationships()->get($name);

        $this->assertSame($relationship, $this->orm->getMapper($entity)->getRelationships()->get($name));
        $left = $this->orm->getMapper(ResolutionChainLeft::class)->getRelationships();
        $right = $this->orm->getMapper(ResolutionChainRight::class)->getRelationships();
        $this->assertInstanceOf(OneToMany::class, $left->get('first'));
        $this->assertSame(['id'], $left->get('first')->getSourceColumns());
        $this->assertSame(['parent_id'], $left->get('first')->getTargetColumns());
        $this->assertInstanceOf(ManyToOne::class, $right->get('second'));
        $this->assertInstanceOf(OneToMany::class, $left->get('third'));
        $this->assertInstanceOf(ManyToOne::class, $right->get('parent'));
    }

    public function chainEntryPointProvider(): array
    {
        return [
            'first inversion' => [ResolutionChainLeft::class, 'first'],
            'second inversion' => [ResolutionChainRight::class, 'second'],
            'third inversion' => [ResolutionChainLeft::class, 'third'],
            'concrete relation' => [ResolutionChainRight::class, 'parent'],
        ];
    }

    /** @dataProvider cycleProvider */
    public function testCycleReportsDependencyPathOnEveryAttempt(string $entity, string $name, array $path): void
    {
        $relationships = $this->orm->getMapper($entity)->getRelationships();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $relationships->get($name);
                $this->fail('A dependency cycle without a concrete relationship must throw');
            } catch (RelationException $exception) {
                $this->assertSame(
                    'Circular relationship dependency: ' . implode(' -> ', $path),
                    $exception->getMessage(),
                );
            }
        }

        // A failed resolution must not contaminate a subsequent valid dependency chain.
        $valid = $this->orm->getMapper(ResolutionChainLeft::class)->getRelationships()->get('first');
        $this->assertInstanceOf(OneToMany::class, $valid);
    }

    public function cycleProvider(): array
    {
        return [
            'direct self dependency' => [
                ResolutionCycleCategory::class,
                'self',
                [ResolutionCycleCategory::class . '.self', ResolutionCycleCategory::class . '.self'],
            ],
            'three self-referencing declarations' => [
                ResolutionCycleCategory::class,
                'first',
                [
                    ResolutionCycleCategory::class . '.first',
                    ResolutionCycleCategory::class . '.second',
                    ResolutionCycleCategory::class . '.third',
                    ResolutionCycleCategory::class . '.first',
                ],
            ],
            'parent entry point' => [
                ResolutionCycleParent::class,
                'children',
                [
                    ResolutionCycleParent::class . '.children',
                    ResolutionCycleChild::class . '.parent',
                    ResolutionCycleParent::class . '.children',
                ],
            ],
            'child entry point' => [
                ResolutionCycleChild::class,
                'parent',
                [
                    ResolutionCycleChild::class . '.parent',
                    ResolutionCycleParent::class . '.children',
                    ResolutionCycleChild::class . '.parent',
                ],
            ],
            'entry outside the cycle' => [
                ResolutionCycleParent::class,
                'entry',
                [
                    ResolutionCycleChild::class . '.parent',
                    ResolutionCycleParent::class . '.children',
                    ResolutionCycleChild::class . '.parent',
                ],
            ],
        ];
    }

    /** @dataProvider initializationFailureProvider */
    public function testInitializationCanRecoverAfterRepeatedFailures(string $hook): void
    {
        $attempts = 0;
        $created = [];
        ResolutionObservedHasMany::${$hook} = static function (?Relationship $relationship = null) use (
            &$attempts,
            &$created,
        ): void {
            $attempts++;
            if (null !== $relationship) {
                $created[] = $relationship;
            }

            if (3 > $attempts) {
                throw new Error('Transient metadata failure');
            }
        };

        $mapper = $this->orm->getMapper(ResolutionObservedCategory::class);
        $stable = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $mapper->getRelationships()->get('children');
                $this->fail('The initialization hook must fail on both initial attempts');
            } catch (Error $exception) {
                $this->assertSame('Transient metadata failure', $exception->getMessage());
            }

            if ('onConstruct' !== $hook) {
                $relationships = $mapper->getRelationships();
                $this->assertCount(3, $relationships);
                $this->assertTrue($relationships->exists('children'));
                $stable ??= $relationships->get('stable');
                $this->assertSame($stable, $relationships->get('stable'));
            }
        }

        $relationships = $mapper->getRelationships();
        $children = $relationships->get('children');
        $this->assertSame(3, $attempts);
        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertTrue($children->getOrphanRemoval());
        $this->assertCount(1, $children->where);
        $this->assertCount(1, $children->group);
        $this->assertCount(1, $children->having);
        $this->assertCount(1, $children->order);
        $this->assertSame(10, $children->limit->getLimit());
        $this->assertSame(2, $children->limit->getOffset());
        $this->assertSame($children, $relationships->get('children'));
        $this->assertInstanceOf(ManyToOne::class, $relationships->get('parent'));

        if ('onConstruct' !== $hook) {
            $this->assertNotSame($created[0], $children);
            $this->assertNotSame($created[1], $children);
            $this->assertSame($created[2], $children);
            $this->assertSame($stable, $relationships->get('stable'));
        }
    }

    public function initializationFailureProvider(): array
    {
        return [
            'attribute discovery' => ['onConstruct'],
            'relationship construction' => ['onAdd'],
            'clause application' => ['onClauses'],
        ];
    }

    public function testReentrantLookupCannotExposeRelationshipDuringClauseApplication(): void
    {
        $relationships = $this->orm->getMapper(ResolutionObservedCategory::class)->getRelationships();
        $observations = 0;
        ResolutionObservedHasMany::$onClauses = function (Relationship $relationship) use (
            $relationships,
            &$observations,
        ): void {
            $this->assertInstanceOf(OneToMany::class, $relationship);
            $this->assertTrue($relationship->getOrphanRemoval());
            $this->assertCount(1, $relationship->order);

            foreach (['children', 'parent'] as $name) {
                try {
                    $relationships->get($name);
                    $this->fail('An unfinished relationship must not be exposed to reentrant lookups');
                } catch (RelationException $exception) {
                    $this->assertStringContainsString('Circular relationship dependency:', $exception->getMessage());
                    $observations++;
                }
            }
        };

        $children = $relationships->get('children');
        $this->assertSame(2, $observations);
        $this->assertSame($children, $relationships->get('children'));
        $this->assertInstanceOf(ManyToOne::class, $relationships->get('parent'));
    }

    public function testInvalidOptionDoesNotLeaveConstructedRelationshipInCache(): void
    {
        $relationships = $this->orm->getMapper(ResolutionInvalidOptionCategory::class)->getRelationships();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $relationships->get('parent');
                $this->fail('Invalid options must be validated on every attempt');
            } catch (RelationException $exception) {
                $this->assertSame('Relationship "parent" does not support orphanRemoval', $exception->getMessage());
            }
        }
    }

    public function testNamedTargetMismatchIsDiagnosedBeforeResolvingDependencies(): void
    {
        $relationships = $this->orm->getMapper(ResolutionCycleParent::class)->getRelationships();

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage(
            sprintf('Relationship not found between "%s" and "%s"', ResolutionCycleParent::class, ResolutionParent::class),
        );
        $relationships->getWith(ResolutionParent::class, 'children');
    }

    /** @dataProvider lookupCacheProvider */
    public function testUnnamedAmbiguityIncludesUnconstructedDeclarations(bool $warmCache): void
    {
        $relationships = new Relationships(ResolutionConcreteFirstCategory::class);
        $relationships->addDeclaration(new OrmAttribute\HasOne(
            ResolutionConcreteFirstCategory::class,
            'concrete',
            ['parent_id' => 'id'],
        ));
        $relationships->addDeclaration(new OrmAttribute\BelongsTo(
            ResolutionConcreteFirstCategory::class,
            'invalid',
            'missing',
        ));
        if ($warmCache) {
            $relationships->get('concrete');
        }

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Ambiguous relationship between');
        $relationships->getWith(ResolutionConcreteFirstCategory::class);
    }

    public function lookupCacheProvider(): array
    {
        return ['cold cache' => [false], 'partially constructed' => [true]];
    }

    public function testUnnamedLookupDoesNotResolveDeclarationsForOtherTargets(): void
    {
        $relationships = new Relationships(ResolutionConcreteFirstCategory::class);
        $relationships->addDeclaration(new OrmAttribute\BelongsTo(
            ResolutionCycleParent::class,
            'unrelated',
            'children',
        ));
        $relationships->addDeclaration(new OrmAttribute\HasOne(
            ResolutionConcreteFirstCategory::class,
            'parent',
            ['parent_id' => 'id'],
        ));

        $this->assertSame('parent', $relationships->getWith(ResolutionConcreteFirstCategory::class)->getName());
        $this->assertCount(2, $relationships);
    }

    public function testColumnFilteredLookupSkipsUnrelatedInProgressRelationship(): void
    {
        $relationships = new Relationships(ResolutionObservedCategory::class);
        $relationships->addDeclaration(new ResolutionObservedHasMany(
            ResolutionObservedCategory::class,
            'children',
            ['id' => 'parent_id'],
        ));
        $relationships->addDeclaration(new OrmAttribute\HasOne(
            ResolutionObservedCategory::class,
            'parent',
            ['parent_id' => 'id'],
        ));
        $found = null;
        ResolutionObservedHasMany::$onClauses = static function () use ($relationships, &$found): void {
            $found = $relationships->getWith(ResolutionObservedCategory::class, columns: ['parent_id']);
        };

        $this->assertInstanceOf(OneToMany::class, $relationships->get('children'));
        $this->assertSame($relationships->get('parent'), $found);
        $this->assertCount(2, $relationships);
    }

    /** @dataProvider lookupCacheProvider */
    public function testColumnFilterSelectsAmongDeclaredRelationships(bool $warmCache): void
    {
        $relationships = $this->orm->getMapper(ResolutionInverseFirstCategory::class)->getRelationships();
        if ($warmCache) {
            $relationships->get('children');
        }

        $parent = $relationships->getWith(ResolutionInverseFirstCategory::class, columns: ['parent_id']);
        $children = $relationships->getWith(ResolutionInverseFirstCategory::class, columns: ['id']);
        $this->assertSame($relationships->get('parent'), $parent);
        $this->assertSame($relationships->get('children'), $children);
        // A supplied name historically takes precedence over the optional column filter.
        $this->assertSame(
            $parent,
            $relationships->getWith(ResolutionInverseFirstCategory::class, 'parent', ['id']),
        );
    }

    public function testColumnFilterRetainsAmbiguityDiagnostic(): void
    {
        $relationships = new Relationships(ResolutionConcreteFirstCategory::class);
        foreach (['first', 'second'] as $name) {
            $relationships->addDeclaration(new OrmAttribute\HasOne(
                ResolutionConcreteFirstCategory::class,
                $name,
                ['parent_id' => 'id'],
            ));
        }

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Ambiguous relationship between');
        $relationships->getWith(ResolutionConcreteFirstCategory::class, columns: ['parent_id']);
    }

    public function testColumnFilterRetainsNotFoundDiagnostic(): void
    {
        $relationships = $this->orm->getMapper(ResolutionInverseFirstCategory::class)->getRelationships();

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship not found between');
        $relationships->getWith(ResolutionInverseFirstCategory::class, columns: ['unknown']);
    }

    public function testProgrammaticReplacementControlsTargetLookupAndCount(): void
    {
        $relationships = new Relationships(ResolutionParent::class);
        $relationships->addDeclaration(new OrmAttribute\HasOne(ResolutionChild::class, '0', ['id' => 'id']));
        $replacement = $relationships->hasOne(ResolutionConcreteFirstCategory::class, '0', ['id' => 'id']);

        $this->assertCount(1, $relationships);
        $this->assertTrue($relationships->exists('0'));
        $this->assertSame($replacement, $relationships->get('0'));
        $this->assertSame($replacement, $relationships->getWith(ResolutionConcreteFirstCategory::class));
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship not found between');
        $relationships->getWith(ResolutionChild::class);
    }

    public function testCustomRelationshipAttributeRetainsConstructionAndClauseHooks(): void
    {
        $relationships = $this->orm->getMapper(ResolutionCustomAttributeCategory::class)->getRelationships();
        $children = $relationships->get('children');

        $this->assertInstanceOf(OneToMany::class, $children);
        $this->assertSame(['id'], $children->getSourceColumns());
        $this->assertSame(['parent_id'], $children->getTargetColumns());
        $this->assertCount(2, $children->order);
        $this->assertSame($children, $relationships->get('children'));
        $this->assertCount(2, $relationships);
    }

    public function testInverseResolutionUsesCustomMapperExtensions(): void
    {
        $child = $this->orm->getMapper(ResolutionMapperChild::class)->getRelationships();
        $parent = $child->get('parent');
        $mapper = $this->orm->getMapper(ResolutionMapperParent::class);
        $relationships = $mapper->getRelationships();

        $this->assertInstanceOf(ResolutionCustomMapper::class, $mapper);
        $this->assertInstanceOf(ManyToOne::class, $parent);
        $this->assertSame(['parent_id'], $parent->getSourceColumns());
        $this->assertCount(2, $relationships);
        $this->assertCount(1, $relationships->get('attribute')->order);
        $this->assertCount(1, $relationships->get('programmatic')->order);
        $this->assertSame($relationships, $mapper->getRelationships());
        $this->assertSame($parent, $child->get('parent'));
    }

    public function testLoadingCollectionSetsInverseUsingColumnFilteredLookup(): void
    {
        $this->orm->getConnection()->execute('INSERT INTO resolution_category VALUES (1, NULL), (2, 1)');
        $parent = ResolutionInverseFirstCategory::find(1);
        $children = $parent->children;

        $this->assertCount(1, $children);
        foreach ($children as $child) {
            $this->assertCount(1, $child->getRelated());
            $this->assertSame($parent, $child->parent);
        }
    }
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\HasOne(ResolutionConcreteFirstCategory::class, 'parent', ['parent_id' => 'id'])]
#[OrmAttribute\BelongsTo(ResolutionConcreteFirstCategory::class, 'children', 'parent')]
class ResolutionConcreteFirstCategory extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\BelongsTo(ResolutionInverseFirstCategory::class, 'children', 'parent')]
#[OrmAttribute\HasOne(ResolutionInverseFirstCategory::class, 'parent', ['parent_id' => 'id'])]
class ResolutionInverseFirstCategory extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\HasOne(ResolutionInvalidCategory::class, 'parent', ['parent_id' => 'id'])]
#[OrmAttribute\BelongsTo(ResolutionInvalidCategory::class, 'children', 'missing')]
class ResolutionInvalidCategory extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_parent')]
#[OrmAttribute\BelongsTo(ResolutionChild::class, 'children', 'parent')]
class ResolutionParent extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_child')]
#[OrmAttribute\BelongsTo(ResolutionParent::class, 'children', 'children')]
#[OrmAttribute\HasOne(ResolutionParent::class, 'parent', ['parent_id' => 'id'])]
class ResolutionChild extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_parent')]
#[OrmAttribute\BelongsTo(ResolutionChainRight::class, 'first', 'second')]
#[OrmAttribute\BelongsTo(ResolutionChainRight::class, 'third', 'parent')]
class ResolutionChainLeft extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_child')]
#[OrmAttribute\BelongsTo(ResolutionChainLeft::class, 'second', 'third')]
#[OrmAttribute\HasOne(ResolutionChainLeft::class, 'parent', ['parent_id' => 'id'])]
class ResolutionChainRight extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\BelongsTo(ResolutionCycleCategory::class, 'self', 'self')]
#[OrmAttribute\BelongsTo(ResolutionCycleCategory::class, 'first', 'second')]
#[OrmAttribute\BelongsTo(ResolutionCycleCategory::class, 'second', 'third')]
#[OrmAttribute\BelongsTo(ResolutionCycleCategory::class, 'third', 'first')]
class ResolutionCycleCategory extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_parent')]
#[OrmAttribute\BelongsTo(ResolutionCycleChild::class, 'entry', 'parent')]
#[OrmAttribute\BelongsTo(ResolutionCycleChild::class, 'children', 'parent')]
class ResolutionCycleParent extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_child')]
#[OrmAttribute\BelongsTo(ResolutionCycleParent::class, 'parent', 'children')]
class ResolutionCycleChild extends MagicEntity
{
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ResolutionObservedHasMany extends OrmAttribute\HasMany
{
    public static ?Closure $onConstruct = null;
    public static ?Closure $onAdd = null;
    public static ?Closure $onClauses = null;

    public function __construct(string $target, string $name, ?array $columns = null, ...$params)
    {
        parent::__construct($target, $name, $columns, ...$params);
        if (null !== self::$onConstruct) {
            (self::$onConstruct)();
        }
    }

    protected function addRelationship(Relationships $relationships): Relationship
    {
        $relationship = parent::addRelationship($relationships);
        if (null !== self::$onAdd) {
            (self::$onAdd)($relationship);
        }

        return $relationship;
    }

    protected function addClauses(Relationship $relationship): void
    {
        parent::addClauses($relationship);
        if (null !== self::$onClauses) {
            (self::$onClauses)($relationship);
        }
    }
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\HasOne(ResolutionObservedCategory::class, 'stable', ['parent_id' => 'id'])]
#[OrmAttribute\BelongsTo(ResolutionObservedCategory::class, 'parent', 'children')]
#[ResolutionObservedHasMany(
    ResolutionObservedCategory::class,
    'children',
    ['id' => 'parent_id'],
    orphanRemoval: true,
    where: ['id' => 1],
    groupBy: ['id'],
    having: ['id' => 1],
    orderBy: ['id' => 'DESC'],
    limit: [10, 2],
)]
class ResolutionObservedCategory extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_category')]
#[OrmAttribute\HasOne(ResolutionInvalidOptionCategory::class, 'parent', ['parent_id' => 'id'], orphanRemoval: true)]
class ResolutionInvalidOptionCategory extends MagicEntity
{
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ResolutionCustomInverse extends OrmAttribute\RelationshipAttribute
{
    protected function addRelationship(Relationships $relationships): Relationship
    {
        return $relationships->belongsTo($this->target, $this->name, $this->params['foreignName']);
    }

    protected function addClauses(Relationship $relationship): void
    {
        parent::addClauses($relationship);
        $relationship->orderBy('parent_id');
    }
}

#[OrmAttribute\Table('resolution_category')]
#[ResolutionCustomInverse(
    ResolutionCustomAttributeCategory::class,
    'children',
    ['foreignName' => 'parent', 'orderBy' => ['id']],
)]
#[OrmAttribute\HasOne(ResolutionCustomAttributeCategory::class, 'parent', ['parent_id' => 'id'])]
class ResolutionCustomAttributeCategory extends MagicEntity
{
}

class ResolutionCustomMapper extends MagicMapper
{
    public function getRelationships(): Relationships
    {
        if (null !== $this->relationships) {
            return $this->relationships;
        }

        $relationships = parent::getRelationships();
        $relationships->get('attribute')->orderBy('id');
        $relationships->hasMany(ResolutionMapperChild::class, 'programmatic', ['id' => 'parent_id'])->orderBy('id');

        return $relationships;
    }
}

#[OrmAttribute\Table('resolution_parent')]
#[OrmAttribute\Mapper(ResolutionCustomMapper::class)]
#[OrmAttribute\HasMany(ResolutionMapperChild::class, 'attribute', ['id' => 'parent_id'])]
class ResolutionMapperParent extends MagicEntity
{
}

#[OrmAttribute\Table('resolution_child')]
#[OrmAttribute\BelongsTo(ResolutionMapperParent::class, 'parent', 'programmatic')]
class ResolutionMapperChild extends MagicEntity
{
}
