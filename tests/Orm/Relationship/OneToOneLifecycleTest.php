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

use Hector\Connection\Connection;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToOne;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\Generator\Sqlite;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

class OneToOneLifecycleTest extends TestCase
{
    private Connection $connection;
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

        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('PRAGMA foreign_keys = ON');
        foreach ([
            'CREATE TABLE one_parent (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)',
            'CREATE TABLE one_child (id INTEGER PRIMARY KEY AUTOINCREMENT, '
                . 'parent_id INTEGER UNIQUE REFERENCES one_parent(id) ON DELETE CASCADE, '
                . "payload TEXT NOT NULL CHECK (payload <> 'invalid'), active INTEGER DEFAULT 1)",
            'CREATE TABLE one_required (id INTEGER PRIMARY KEY AUTOINCREMENT, '
                . 'parent_id INTEGER NOT NULL UNIQUE REFERENCES one_parent(id) ON DELETE CASCADE, '
                . "payload TEXT NOT NULL CHECK (payload <> 'invalid'))",
            'CREATE TABLE one_shared (parent_id INTEGER PRIMARY KEY REFERENCES one_parent(id), payload TEXT)',
            'CREATE TABLE one_composite_parent '
                . '(tenant TEXT, reference INTEGER, name TEXT, PRIMARY KEY (tenant, reference))',
            'CREATE TABLE one_composite_child (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant TEXT, '
                . 'parent_ref INTEGER, payload TEXT, UNIQUE (tenant, parent_ref), '
                . 'FOREIGN KEY (tenant, parent_ref) REFERENCES one_composite_parent(tenant, reference))',
            'CREATE TABLE one_plain_parent (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE one_plain_child (id INTEGER PRIMARY KEY, parent_id INTEGER, payload TEXT)',
            'CREATE TABLE one_ambiguous_parent (id INTEGER PRIMARY KEY)',
            'CREATE TABLE one_ambiguous_child (id INTEGER PRIMARY KEY, '
                . 'parent_id INTEGER REFERENCES one_ambiguous_parent(id), '
                . 'other_parent_id INTEGER REFERENCES one_ambiguous_parent(id))',
            'CREATE TABLE one_left (id INTEGER PRIMARY KEY REFERENCES one_right(id))',
            'CREATE TABLE one_right (id INTEGER PRIMARY KEY REFERENCES one_left(id))',
            'CREATE TABLE one_named_parent (parent_key INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE one_named_child (child_key INTEGER PRIMARY KEY, parent_key INTEGER, payload TEXT)',
            'CREATE TABLE one_optional_parent (id INTEGER PRIMARY KEY, link_key TEXT UNIQUE)',
            'CREATE TABLE one_optional_child (id INTEGER PRIMARY KEY, link_key TEXT UNIQUE, payload TEXT)',
            'CREATE TABLE one_string_child (id TEXT PRIMARY KEY, '
                . 'parent_id INTEGER UNIQUE REFERENCES one_parent(id), payload TEXT)',
        ] as $sql) {
            $this->connection->execute($sql);
        }

        $this->connection->execute("INSERT INTO one_parent VALUES (1, 'first'), (2, 'second'), (3, 'empty')");
        $this->connection->execute(
            "INSERT INTO one_child VALUES (10, 1, 'old', 1), (11, 2, 'hidden', 0), (12, NULL, 'orphan', 1)",
        );
        $this->connection->execute("INSERT INTO one_required VALUES (30, 1, 'required')");
        $this->connection->execute("INSERT INTO one_shared VALUES (2, 'shared')");
        $this->connection->execute("INSERT INTO one_composite_parent VALUES ('t', 0, 'composite')");
        $this->connection->execute("INSERT INTO one_composite_child VALUES (50, 't', 0, 'old')");
        $this->connection->execute('INSERT INTO one_optional_parent VALUES (1, NULL)');
        $this->connection->execute("INSERT INTO one_optional_child VALUES (1, NULL, 'unowned')");
        $this->connection->execute("INSERT INTO one_string_child VALUES ('01', 1, 'old'), ('1', 2, 'other')");

        $this->orm = new Orm($this->connection, (new Sqlite($this->connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    public function testNewParentAndRequiredChildSaveInDependencyOrder(): void
    {
        $parent = new OneParent();
        $parent->name = 'new';
        $child = new OneRequiredChild();
        $child->payload = 'new';
        $parent->required = $child;
        $parent->save();

        $this->assertNotNull($parent->id);
        $this->assertNotNull($child->id);
        $this->assertSame($parent->id, $child->parent_id);
        $parent->getRelated()->unset('required');
        $this->assertInstanceOf(OneRequiredChild::class, $parent->required);
        $this->assertSame($child->id, $parent->required->id);
    }

    public function testBidirectionalSaveCanStartAtEitherSide(): void
    {
        foreach ([true, false] as $startAtParent) {
            $parent = new OneParent();
            $parent->name = 'bidirectional';
            $child = new OneRequiredChild();
            $child->payload = 'new';
            $parent->required = $child;
            $child->parent = $parent;

            ($startAtParent ? $parent : $child)->save();

            $this->assertNotNull($parent->id);
            $this->assertNotNull($child->id);
            $this->assertSame($parent->id, $child->parent_id);
            $this->assertSame($parent->id, $child->parent->id);
        }

        $this->assertCount(5, $this->connection->fetchAll('SELECT * FROM one_parent'));
        $this->assertCount(3, $this->connection->fetchAll('SELECT * FROM one_required'));
    }

    public function testExistingParentReceivesNewChild(): void
    {
        $parent = OneParent::find(3);
        $parent->child = $this->newChild();
        $parent->save();
        $this->assertSame(3, $parent->child->parent_id);
        $this->assertNotNull($parent->child->id);
    }

    public function testLazyAndEagerLoadingReturnScalarOrNull(): void
    {
        $this->assertNull(OneParent::find(3)->child);
        $parents = OneParent::query()->orderBy('id')->with(['child'])->all();
        $this->assertInstanceOf(OneChild::class, $parents[0]->child);
        $this->assertSame(10, $parents[0]->child->id);
        $this->assertSame(11, $parents[1]->child->id);
        $this->assertNull($parents[2]->child);
    }

    public function testReadingAbsentChildDoesNotScheduleRemoval(): void
    {
        $parent = OneParent::find(2);
        $this->assertNull($parent->activeChild);
        $this->assertNull($parent->getRelated()->getAssignment('activeChild'));
        $parent->save();
        $this->assertNotNull($this->childRow(11));
    }

    public function testNullDetachesLoadedAndUnloadedChildrenWithoutDeletingThem(): void
    {
        foreach ([1, 2] as $id) {
            $parent = OneParent::find($id);
            if (1 === $id) {
                $this->assertSame(1, $parent->child->parent->id);
            }
            $parent->child = null;
            $parent->save();
            $this->assertNull($this->childRow(9 + $id)['parent_id']);
            $this->assertNull($parent->getRelated()->getAssignment('child'));
            $parent->getRelated()->unset('child');
            $this->assertNull($parent->child);
        }
    }

    public function testOrphanRemovalDeletesLoadedAndUnloadedChildren(): void
    {
        foreach ([1, 2] as $id) {
            $parent = OneParent::find($id);
            if (1 === $id) {
                $this->assertInstanceOf(OneChild::class, $parent->owned);
            }
            $parent->owned = null;
            $parent->save();
            $parent->save();
            $this->assertNull($this->childRow(9 + $id));
        }

        $this->assertNotNull($this->childRow(12));
    }

    public function testRequiredChildRejectsDetachmentWithoutOrphanRemoval(): void
    {
        $parent = OneParent::find(1);
        $this->relation('required')->setOrphanRemoval(false);
        $parent->required = null;
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('cannot be cleared');

        try {
            $parent->save();
        } finally {
            $this->assertNotNull($this->connection->fetchOne('SELECT * FROM one_required WHERE id = 30'));
            $this->assertNotNull($parent->getRelated()->getAssignment('required'));
        }
    }

    public function testReplacementDetachesOldChildBeforeUniqueLinkIsReused(): void
    {
        $parent = OneParent::find(1);
        $parent->child = $this->newChild();
        $parent->save();
        $this->assertNull($this->childRow(10)['parent_id']);
        $this->assertSame(1, $parent->child->parent_id);
    }

    public function testReplacementDeletesOldChildBeforeUniqueLinkIsReused(): void
    {
        $parent = OneParent::find(1);
        $old = $parent->owned;
        $parent->owned = $this->newChild();
        $parent->save();
        $this->assertNull($this->childRow(10));
        $this->assertNull($this->orm->getStatus($old));
        $this->assertSame(1, $parent->owned->parent_id);
    }

    public function testSameEntityReassignmentDoesNotDeleteIt(): void
    {
        $parent = OneParent::find(1);
        $child = $parent->owned;
        $parent->owned = null;
        $parent->owned = $child;
        $parent->save();
        $this->assertNotNull($this->childRow(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($child));
    }

    public function testSameDatabaseEntityInAnotherInstanceIsNotDeleted(): void
    {
        $parent = OneParent::find(1);
        $this->assertSame(10, $parent->owned->id);
        $parent->owned = OneChild::find(10);
        $parent->save();
        $this->assertNotNull($this->childRow(10));
    }

    public function testIntermediatePersistedAssignmentDoesNotDeleteAnotherParentsChild(): void
    {
        $parent = OneParent::find(1);
        $this->assertSame(10, $parent->owned->id);
        $parent->owned = OneChild::find(11);
        $parent->owned = $this->newChild();
        $parent->save();
        $this->assertNull($this->childRow(10));
        $this->assertSame(2, (int)$this->childRow(11)['parent_id']);
    }

    public function testRemovedTransientAssignmentCancelsQueuedInsertBeforeBatch(): void
    {
        $parent = new OneParent();
        $parent->name = 'new';
        $child = $this->newChild('invalid');
        $parent->owned = $child;
        $parent->owned = null;
        $this->orm->save($child);
        $this->orm->save($parent);
        $this->orm->persist();
        $this->assertNull($child->id);
        $this->assertNull($this->orm->getStatus($child));
        $this->assertNotNull($parent->id);
    }

    public function testFailedReplacementRestoresOldEntityAndPendingAssignment(): void
    {
        $parent = OneParent::find(1);
        $old = $parent->owned;
        $replacement = $this->newChild('invalid');
        $parent->owned = $replacement;

        try {
            $parent->save();
            $this->fail('Invalid replacement should fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNotNull($this->childRow(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($old));
        $this->assertFalse($old->isAltered());
        $this->assertNull($replacement->id);
        $this->assertNull($replacement->parent_id);
        $this->assertSame($old, $parent->getRelated()->getAssignment('owned')['previous']);

        $replacement->payload = 'valid';
        $parent->save();
        $this->assertNull($this->childRow(10));
        $this->assertSame(1, $replacement->parent_id);
    }

    public function testFilteredUnloadedAssignmentDoesNotDeleteHiddenChild(): void
    {
        $parent = OneParent::find(2);
        $parent->activeChild = null;
        $parent->save();
        $this->assertNotNull($this->childRow(11));

        $parent->activeChild = $this->newChild();
        try {
            $parent->save();
            $this->fail('A hidden child still owns the unique link');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('UNIQUE', $exception->getMessage());
        }

        $this->assertSame('hidden', $this->childRow(11)['payload']);
        $this->assertNull($parent->activeChild->id);
    }

    public function testEagerLoadingDoesNotOverwriteAnExplicitAssignment(): void
    {
        $parent = OneParent::find(1);
        $replacement = $this->newChild();
        $parent->owned = $replacement;
        $this->relation('owned')->get($parent);
        $this->assertSame($replacement, $parent->owned);
        $parent->save();
        $this->assertNull($this->childRow(10));
    }

    public function testCacheInvalidationDiscardsPendingAssignmentWithoutDeleting(): void
    {
        $parent = OneParent::find(1);
        $parent->owned = null;
        $parent->getRelated()->unset('owned');
        $parent->save();
        $this->assertNotNull($this->childRow(10));
        $this->assertSame(10, $parent->owned->id);
    }

    public function testExistingChildNonLinkChangesRequireCascade(): void
    {
        $parent = OneParent::find(1);
        $parent->child->payload = 'changed';
        $parent->save();
        $this->assertSame('old', $this->childRow(10)['payload']);
        $parent->save(cascade: true);
        $this->assertSame('changed', $this->childRow(10)['payload']);
    }

    public function testCompositeMappingSupportsZeroAndReplacement(): void
    {
        $parent = OneCompositeParent::query()->whereEquals(['tenant' => 't', 'reference' => 0])->get();
        $this->assertSame(50, $parent->child->id);
        $replacement = new OneCompositeChild();
        $replacement->payload = 'new';
        $parent->child = $replacement;
        $parent->save();
        $this->assertSame('t', $replacement->tenant);
        $this->assertSame(0, $replacement->parent_ref);
        $this->assertNull($this->connection->fetchOne('SELECT * FROM one_composite_child WHERE id = 50'));
    }

    public function testSharedPrimaryKeyCanBeInsertedAndReplacedButNotDetached(): void
    {
        $parent = OneParent::find(2);
        $parent->shared = null;
        try {
            $parent->save();
            $this->fail('A primary key must not be cleared');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('cannot be cleared', $exception->getMessage());
        }

        $this->relation('shared')->setOrphanRemoval(true);
        $child = new OneSharedChild();
        $child->payload = 'replacement';
        $parent->shared = $child;
        $parent->save();
        $this->assertSame(2, $child->parent_id);
        $this->assertSame('replacement', $this->connection->fetchOne('SELECT * FROM one_shared')['payload']);
    }

    public function testMissingSourceKeyNeverMatchesUnownedChildren(): void
    {
        $parent = OneOptionalParent::find(1);
        $this->assertNull($parent->child);
        $parent->getRelated()->unset('child');
        $parent->child = null;
        $parent->save();
        $this->assertNotNull($this->connection->fetchOne('SELECT * FROM one_optional_child WHERE id = 1'));
    }

    public function testTargetSubclassesAreValidButCollectionsAreNot(): void
    {
        $parent = OneParent::find(3);
        $child = new OneChildSubclass();
        $child->payload = 'subclass';
        $parent->child = $child;
        $parent->save();
        $this->assertSame(3, $child->parent_id);
        $this->expectException(InvalidArgumentException::class);
        $parent->child = new Collection([$child]);
    }

    public function testWrongTargetTypeIsRejected(): void
    {
        $parent = OneParent::find(3);
        $this->expectException(InvalidArgumentException::class);
        $parent->child = new OneSharedChild();
    }

    public function testDirectionAndMappingAreInferredFromBothSides(): void
    {
        $parentSide = new OneToOne('child', OneParent::class, OneChild::class);
        $childSide = new OneToOne('parent', OneChild::class, OneParent::class);
        $this->assertTrue($parentSide->isParent());
        $this->assertNull($parentSide->getConfiguredIsParent());
        $this->assertFalse($childSide->isParent());
        $this->assertSame(['id'], $parentSide->getSourceColumns());
        $this->assertSame(['parent_id'], $parentSide->getTargetColumns());
        $this->assertSame(['parent_id'], $childSide->getSourceColumns());
        $this->assertSame(['id'], $childSide->getTargetColumns());
    }

    public function testExplicitDirectionWinsAndReverseDoesNotCopyRemovalPolicy(): void
    {
        $relation = $this->relation('owned');
        $reverse = $relation->reverse('parent');
        $this->assertFalse($reverse->isParent());
        $this->assertNull($reverse->getOrphanRemoval());
        $again = $reverse->reverse('owned');
        $this->assertTrue($again->isParent());
        $this->assertFalse($again->getOrphanRemoval());
        $this->assertSame($relation->getSourceColumns(), $again->getSourceColumns());

        $forced = new OneToOne('child', OneParent::class, OneChild::class, ['id' => 'parent_id'], isParent: false);
        $this->assertFalse($forced->isParent());
    }

    public function testMissingFkKeepsHistoricalModeInBothDirections(): void
    {
        $relation = new OneToOne('child', OnePlainParent::class, OnePlainChild::class, ['id' => 'parent_id']);
        $this->assertNull($relation->isParent());
        $this->assertNull($relation->reverse('parent')->isParent());
        $this->assertFalse($relation->hasLifecyclePolicy());
    }

    public function testOpposingFksKeepHistoricalModeUnlessDirectionIsExplicit(): void
    {
        $relation = new OneToOne('right', OneLeft::class, OneRight::class);
        $this->assertNull($relation->isParent());
        $this->assertNull($relation->reverse('left')->isParent());
        $forced = new OneToOne('right', OneLeft::class, OneRight::class, isParent: true);
        $this->assertTrue($forced->isParent());
    }

    public function testMultipleFkMappingsRequireExplicitColumns(): void
    {
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Ambiguous columns');
        new OneToOne('child', OneAmbiguousParent::class, OneAmbiguousChild::class);
    }

    public function testExplicitColumnsDisambiguateMultipleFkMappings(): void
    {
        $relation = new OneToOne(
            'child',
            OneAmbiguousParent::class,
            OneAmbiguousChild::class,
            ['id' => 'other_parent_id'],
        );
        $this->assertTrue($relation->isParent());
        $this->assertSame(['other_parent_id'], $relation->getTargetColumns());
    }

    public function testKeyConventionsDependOnTheExplicitRole(): void
    {
        $parentSide = new OneToOne('child', OneNamedParent::class, OneNamedChild::class, isParent: true);
        $legacyChildSide = new OneToOne('parent', OneNamedChild::class, OneNamedParent::class);
        $this->assertSame(['parent_key'], $parentSide->getSourceColumns());
        $this->assertSame(['parent_key'], $parentSide->getTargetColumns());
        $this->assertSame(['parent_key'], $legacyChildSide->getSourceColumns());
        $this->assertNull($legacyChildSide->isParent());
    }

    public function testCompositeFkMatchingIgnoresMappingOrderWithoutMixingPairs(): void
    {
        $relation = new OneToOne(
            'child',
            OneCompositeParent::class,
            OneCompositeChild::class,
            ['tenant' => 'tenant', 'reference' => 'parent_ref'],
        );
        $this->assertTrue($relation->isParent());
        $wrongPairs = new OneToOne(
            'child',
            OneCompositeParent::class,
            OneCompositeChild::class,
            ['tenant' => 'parent_ref', 'reference' => 'tenant'],
        );
        $this->assertNull($wrongPairs->isParent());
    }

    public function testChildSideRejectsOrphanRemoval(): void
    {
        $relation = new OneToOne('parent', OneChild::class, OneParent::class);
        $this->expectException(RelationException::class);
        $relation->setOrphanRemoval(true);
    }

    public function testProgrammaticDeclarationsAndExistingHasOneAreDistinct(): void
    {
        $relations = $this->orm->getMapper(OneParent::class)->getRelationships();
        $child = $relations->hasOneChild(OneChild::class, 'directChild');
        $inferred = $relations->oneToOne(OneChild::class, 'inferredChild');
        $old = $relations->hasOne(OneChild::class, 'legacyReference', ['id' => 'id']);
        $this->assertTrue($child->isParent());
        $this->assertFalse($child->getOrphanRemoval());
        $this->assertTrue($inferred->isParent());
        $this->assertSame(ManyToOne::class, $old::class);
    }

    public function testSharedPrimaryReplacementWithAssignedIdentifierIsStillNew(): void
    {
        $parent = OneParent::find(2);
        $this->relation('shared')->setOrphanRemoval(true);
        $this->assertSame('shared', $parent->shared->payload);
        $child = new OneSharedChild();
        $child->parent_id = 2;
        $child->payload = 'new identity instance';
        $parent->shared = $child;
        $parent->save();
        $this->assertSame(
            'new identity instance',
            $this->connection->fetchOne('SELECT * FROM one_shared WHERE parent_id = 2')['payload'],
        );
    }

    public function testDuplicateChildrenAreRejectedOnReadAndReplacement(): void
    {
        $this->connection->execute("INSERT INTO one_plain_parent VALUES (1, 'one')");
        $this->connection->execute("INSERT INTO one_plain_child VALUES (1, 1, 'a'), (2, 1, 'b')");
        $relationship = $this->orm->getMapper(OnePlainParent::class)->getRelationships()->hasOneChild(
            OnePlainChild::class,
            'child',
            ['id' => 'parent_id'],
            orphanRemoval: true,
        );
        $parent = OnePlainParent::find(1);
        try {
            $relationship->get($parent);
            $this->fail('A one-to-one read must not pick an arbitrary child');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('more than one child', $exception->getMessage());
        }

        $parent->child = null;
        try {
            $parent->save();
            $this->fail('Ambiguous replacement must fail');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('more than one child', $exception->getMessage());
        }

        $this->assertCount(2, $this->connection->fetchAll('SELECT * FROM one_plain_child'));
    }

    public function testFailedNewChildRollsBackGeneratedParentIdentifier(): void
    {
        $parent = new OneParent();
        $parent->name = 'new';
        $child = $this->newChild('invalid');
        $parent->child = $child;
        try {
            $parent->save();
            $this->fail('An invalid child must roll back its new parent');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNull($parent->id);
        $this->assertNull($child->parent_id);
        $this->assertNull($this->orm->getStatus($parent));
        $this->assertNotNull($parent->getRelated()->getAssignment('child'));
        $this->assertCount(3, $this->connection->fetchAll('SELECT * FROM one_parent'));
    }

    public function testCascadeFailureRestoresScalarAssignmentAndEarlierDeletion(): void
    {
        $parent = OneParent::find(1);
        $old = $parent->owned;
        $parent->owned = null;
        $parent->required->payload = 'invalid';
        try {
            $parent->save(cascade: true);
            $this->fail('A failing cascade must restore an earlier scalar removal');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNotNull($this->childRow(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($old));
        $this->assertSame($old, $parent->getRelated()->getAssignment('owned')['previous']);
    }

    public function testChildCannotBeAttachedWithoutCompleteSourceKeys(): void
    {
        $parent = OneOptionalParent::find(1);
        $child = new OneOptionalChild();
        $child->payload = 'new';
        $parent->child = $child;
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('complete parent key values');
        $parent->save();
    }

    public function testDeferredChildQueuedBeforeParentUsesDependencyOrder(): void
    {
        $parent = new OneParent();
        $parent->name = 'queued';
        $child = new OneRequiredChild();
        $child->payload = 'queued';
        $parent->required = $child;
        $this->orm->save($child);
        $this->orm->save($parent);
        $this->orm->persist();
        $this->assertNotNull($parent->id);
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertNotNull($child->id);
        $this->assertCount(4, $this->connection->fetchAll('SELECT * FROM one_parent'));
    }

    public function testChildSideFailureRollsBackANewParentWithoutReciprocalAssignment(): void
    {
        $parent = new OneParent();
        $parent->name = 'new';
        $child = new OneRequiredChild();
        $child->payload = 'invalid';
        $child->parent = $parent;
        try {
            $child->save();
            $this->fail('The new parent must be part of the child-side transaction');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNull($parent->id);
        $this->assertNull($child->parent_id);
        $this->assertNull($this->orm->getStatus($parent));
        $this->assertCount(3, $this->connection->fetchAll('SELECT * FROM one_parent'));
    }

    public function testNumericLookingStringIdentitiesAreNotConflated(): void
    {
        $parent = OneParent::find(1);
        $this->assertSame('01', $parent->stringChild->id);
        $parent->stringChild = OneStringChild::find('1');
        $parent->save();
        $this->assertNull($this->connection->fetchOne('SELECT * FROM one_string_child WHERE id = ?', ['01']));
        $this->assertSame(
            1,
            (int)$this->connection->fetchOne('SELECT * FROM one_string_child WHERE id = ?', ['1'])['parent_id'],
        );
    }

    private function relation(string $name): OneToOne
    {
        return $this->orm->getMapper(OneParent::class)->getRelationships()->get($name);
    }

    private function newChild(string $payload = 'new'): OneChild
    {
        $child = new OneChild();
        $child->payload = $payload;

        return $child;
    }

    private function childRow(int $id): ?array
    {
        return $this->connection->fetchOne('SELECT * FROM one_child WHERE id = ?', [$id]);
    }
}

#[OrmAttribute\Table('one_parent', 'main')]
#[OrmAttribute\HasOneChild(OneChild::class, 'child')]
#[OrmAttribute\HasOneChild(OneChild::class, 'owned', orphanRemoval: true)]
#[OrmAttribute\HasOneChild(OneChild::class, 'activeChild', orphanRemoval: true, where: ['active' => 1])]
#[OrmAttribute\HasOneChild(OneRequiredChild::class, 'required', orphanRemoval: true)]
#[OrmAttribute\HasOneChild(OneSharedChild::class, 'shared')]
#[OrmAttribute\HasOneChild(OneStringChild::class, 'stringChild', orphanRemoval: true)]
class OneParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_child', 'main')]
#[OrmAttribute\BelongsTo(OneParent::class, 'parent', 'child')]
class OneChild extends MagicEntity
{
}

class OneChildSubclass extends OneChild
{
}

#[OrmAttribute\Table('one_required', 'main')]
#[OrmAttribute\BelongsTo(OneParent::class, 'parent', 'required')]
class OneRequiredChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_shared', 'main')]
class OneSharedChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_composite_parent', 'main')]
#[OrmAttribute\HasOneChild(OneCompositeChild::class, 'child', orphanRemoval: true)]
class OneCompositeParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_composite_child', 'main')]
class OneCompositeChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_plain_parent', 'main')]
class OnePlainParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_plain_child', 'main')]
class OnePlainChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_ambiguous_parent', 'main')]
class OneAmbiguousParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_ambiguous_child', 'main')]
class OneAmbiguousChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_left', 'main')]
class OneLeft extends MagicEntity
{
}

#[OrmAttribute\Table('one_right', 'main')]
class OneRight extends MagicEntity
{
}

#[OrmAttribute\Table('one_named_parent', 'main')]
class OneNamedParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_named_child', 'main')]
class OneNamedChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_optional_parent', 'main')]
#[OrmAttribute\HasOneChild(OneOptionalChild::class, 'child', ['link_key' => 'link_key'], orphanRemoval: true)]
class OneOptionalParent extends MagicEntity
{
}

#[OrmAttribute\Table('one_optional_child', 'main')]
class OneOptionalChild extends MagicEntity
{
}

#[OrmAttribute\Table('one_string_child', 'main')]
class OneStringChild extends MagicEntity
{
}
