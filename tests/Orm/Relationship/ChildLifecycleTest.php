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
use Hector\Connection\ConnectionSet;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Event\EntityBeforeDeleteEvent;
use Hector\Orm\Event\EntityBeforeSaveEvent;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Storage\EntityStorage;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\SchemaContainer;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class ChildLifecycleTest extends TestCase
{
    private Connection $connection;
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $property->setAccessible(true);
        $this->previousReflections = $property->getValue();
        $property->setValue(null, []);

        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->connection->execute('CREATE TABLE lifecycle_parent (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $this->connection->execute(
            'CREATE TABLE lifecycle_child (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'parent_id INTEGER REFERENCES lifecycle_parent(id), '
            . "code TEXT NOT NULL CHECK (code <> 'invalid'), UNIQUE (parent_id, code))",
        );
        $this->connection->execute(
            'CREATE TABLE lifecycle_required (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'parent_id INTEGER NOT NULL REFERENCES lifecycle_parent(id), code TEXT)',
        );
        $this->connection->execute("INSERT INTO lifecycle_parent VALUES (1, 'original'), (2, 'other')");
        $this->connection->execute("INSERT INTO lifecycle_child VALUES (10, 1, 'a'), (11, 1, 'b'), (12, 2, 'a')");
        $this->connection->execute("INSERT INTO lifecycle_required VALUES (20, 1, 'required')");
        $this->connection->execute(
            'CREATE TABLE lifecycle_composite_parent (tenant TEXT, ref INTEGER, PRIMARY KEY (tenant, ref))',
        );
        $this->connection->execute(
            'CREATE TABLE lifecycle_composite_child (id INTEGER PRIMARY KEY, tenant TEXT, parent_ref INTEGER, '
            . 'FOREIGN KEY (tenant, parent_ref) REFERENCES lifecycle_composite_parent(tenant, ref))',
        );
        $this->connection->execute("INSERT INTO lifecycle_composite_parent VALUES ('a', 0), ('b', 0)");
        $this->connection->execute("INSERT INTO lifecycle_composite_child VALUES (1, 'a', 0), (2, 'b', 0)");
        $this->connection->execute('CREATE TABLE lifecycle_tag (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute(
            'CREATE TABLE lifecycle_link (parent_id INTEGER, tag_id INTEGER, PRIMARY KEY (parent_id, tag_id))',
        );
        $this->connection->execute("INSERT INTO lifecycle_tag VALUES (1, 'shared')");
        $this->connection->execute('INSERT INTO lifecycle_link VALUES (1, 1), (2, 1)');
        $other = new Connection('sqlite::memory:', name: 'other');
        $other->execute('CREATE TABLE lifecycle_child (id INTEGER PRIMARY KEY, parent_id INTEGER)');
        $schemas = new SchemaContainer([
            ...(new Sqlite($this->connection))->generateSchemas('main'),
            ...(new Sqlite($other))->generateSchemas('main'),
        ]);
        $this->orm = new Orm(new ConnectionSet($this->connection, $other), $schemas);
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $property->setAccessible(true);
        $property->setValue(null, $this->previousReflections);
    }

    public function testExplicitDetachmentPreservesChildAndClearsCachedParent(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->children;
        $child = $children->first();
        $this->assertSame(1, $child->parent->id);
        unset($children[0]);
        $parent->save();

        $this->assertNull($this->row(10)['parent_id']);
        $this->assertNull($child->parent_id);
        $this->assertNull($child->parent);
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($child));
        $this->assertSame([], iterator_to_array($children->detached()));
    }

    public function testExplicitOrphanRemovalDeletesOnlyRemovedChild(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $child = $children->first();
        unset($children[0]);
        $parent->save();
        $parent->save();

        $this->assertNull($this->row(10));
        $this->assertNotNull($this->row(11));
        $this->assertNull($this->orm->getStatus($child));
    }

    public function testOmittedPolicyPreservesHistoricalDeletion(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->legacy;
        unset($children[0]);
        $parent->save();
        $this->assertNull($this->row(10));
        $relation = $this->orm->getMapper($parent)->getRelationships()->get('legacy');
        $this->assertNull($relation->getOrphanRemoval());
    }

    public function testRemoveThenReattachSameChildDoesNotDeleteIt(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $child = $children->first();
        unset($children[0]);
        $children[] = $child;
        $parent->save();
        $this->assertSame(1, (int)$this->row(10)['parent_id']);
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($child));
    }

    public function testTransientChildRemovedBeforeSaveProducesNoDelete(): void
    {
        $parent = LifecycleParent::find(1);
        $child = new LifecycleChild();
        $child->code = 'new';
        $children = $parent->owned;
        $children[] = $child;
        unset($children[2]);
        $parent->save();
        $this->assertNull($child->id);
        $this->assertNull($this->orm->getStatus($child));
        $this->assertCount(3, $this->connection->fetchAll('SELECT * FROM lifecycle_child'));
    }

    public function testRequiredForeignKeyRejectsDetachAndRestoresPendingState(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->required;
        $child = $children->first();
        unset($children[0]);
        $parent->name = 'pending';

        try {
            $parent->save();
            $this->fail('Required child should not be detached');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('cannot be cleared', $exception->getMessage());
        }

        $this->assertSame(
            'original',
            $this->connection->fetchOne('SELECT name FROM lifecycle_parent WHERE id = 1')['name'],
        );
        $this->assertSame('pending', $parent->name);
        $this->assertTrue($parent->isAltered('name'));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($parent));
        $this->assertSame(1, $child->parent_id);
        $this->assertCount(1, iterator_to_array($children->detached()));

        $this->orm->getMapper($parent)->getRelationships()->get('required')->setOrphanRemoval(true);
        $parent->save();
        $this->assertNull($this->connection->fetchOne('SELECT * FROM lifecycle_required WHERE id = 20'));
    }

    public function testReplacementReleasesUniqueLinkBeforeInsertion(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $replacement = new LifecycleChild();
        $replacement->code = 'a';
        $children[0] = $replacement;
        $parent->save();

        $this->assertNull($this->row(10));
        $this->assertNotNull($replacement->id);
        $this->assertSame(1, $replacement->parent_id);
    }

    public function testFailedReplacementRollsBackAndCanBeRetried(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $old = $children->first();
        $replacement = new LifecycleChild();
        $replacement->code = 'invalid';
        $children[0] = $replacement;

        try {
            $parent->save();
            $this->fail('Invalid replacement must fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNotNull($this->row(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($old));
        $this->assertFalse($old->isAltered());
        $this->assertNull($this->orm->getStatus($replacement));
        $this->assertNull($replacement->id);
        $this->assertNull($replacement->parent_id);
        $this->assertCount(1, iterator_to_array($children->detached()));

        $replacement->code = 'a';
        $parent->save();
        $this->assertNull($this->row(10));
        $this->assertSame(1, $replacement->parent_id);
    }

    public function testParentInsertAndFirstChildAreRolledBackWhenNextChildFails(): void
    {
        $parent = new LifecycleParent();
        $parent->name = 'new';
        $first = new LifecycleChild();
        $first->code = 'valid';
        $second = new LifecycleChild();
        $second->code = 'invalid';
        $parent->owned = new Collection([$first, $second]);

        try {
            $parent->save();
            $this->fail('Invalid child must fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }

        $this->assertNull($parent->id);
        $this->assertNull($first->id);
        $this->assertNull($first->parent_id);
        $this->assertNull($this->orm->getStatus($parent));
        $this->assertNull($this->orm->getStatus($first));
        $this->assertCount(2, $this->connection->fetchAll('SELECT * FROM lifecycle_parent'));
        $second->code = 'valid2';
        $parent->save();
        $this->assertNotNull($first->id);
        $this->assertSame($parent->id, $first->parent_id);
    }

    public function testFilteredCollectionReplacementNeverRemovesUnseenChildren(): void
    {
        $parent = LifecycleParent::find(1);
        $this->assertCount(1, $parent->filtered);
        $parent->filtered = new Collection();
        $parent->save();
        $this->assertNull($this->row(10));
        $this->assertSame(1, (int)$this->row(11)['parent_id']);
    }

    public function testUnloadedCollectionAssignmentIsAdditiveAndCacheUnsetIsNotRemoval(): void
    {
        $parent = LifecycleParent::find(1);
        $parent->owned = new Collection();
        $parent->save();
        $this->assertNotNull($this->row(10));
        $parent->getRelated()->unset('owned');
        $this->assertCount(2, $parent->owned);
        $parent->getRelated()->unset('owned');
        $parent->save();
        $this->assertNotNull($this->row(11));
    }

    public function testNullAssignmentRemovesOnlyPreviouslyLoadedCollectionMembers(): void
    {
        $parent = LifecycleParent::find(1);
        $this->assertCount(2, $parent->children);
        $parent->children = null;
        $parent->save();
        $this->assertNull($this->row(10)['parent_id']);
        $this->assertNull($this->row(11)['parent_id']);
        $this->assertSame(2, (int)$this->row(12)['parent_id']);
    }

    public function testHydrationDoesNotScheduleCollectionRemoval(): void
    {
        $parent = LifecycleParent::find(1);
        $this->assertCount(2, $parent->owned);
        $parent->getRelated()->setLoaded('owned', new Collection());
        $parent->save();
        $this->assertNotNull($this->row(10));
        $this->assertNotNull($this->row(11));
    }

    public function testSavepointDoesNotRollbackCallerTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->connection->execute("UPDATE lifecycle_parent SET name = 'outer' WHERE id = 2");
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $invalid = new LifecycleChild();
        $invalid->code = 'invalid';
        $children[0] = $invalid;
        try {
            $parent->save();
            $this->fail('Invalid child must fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }
        $this->assertTrue($this->connection->inTransaction());
        $this->assertNotNull($this->row(10));
        $this->assertSame(
            'outer',
            $this->connection->fetchOne('SELECT name FROM lifecycle_parent WHERE id = 2')['name'],
        );
        $this->connection->rollBack();
    }

    public function testBatchFailureRestoresEarlierLifecycleWrites(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $old = $children->first();
        unset($children[0]);
        $invalid = new LifecycleChild();
        $invalid->code = 'invalid';
        $this->orm->save($parent);
        $this->orm->save($invalid);
        try {
            $this->orm->persist();
            $this->fail('Batch must fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }
        $this->assertNotNull($this->row(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($old));
        $this->assertSame(EntityStorage::STATUS_TO_UPDATE, $this->orm->getStatus($parent));
        $this->assertSame(EntityStorage::STATUS_TO_INSERT, $this->orm->getStatus($invalid));
        $invalid->code = 'valid';
        $this->orm->persist();
        $this->assertNull($this->row(10));
    }

    public function testUnsupportedLifecycleOptionIsRejectedAndReverseDoesNotCopyIt(): void
    {
        $relation = $this->orm->getMapper(LifecycleParent::class)->getRelationships()->get('owned');
        $reverse = $relation->reverse('parent');
        $this->assertFalse($reverse->hasLifecyclePolicy());
        $this->expectException(RelationException::class);
        $reverse->setOrphanRemoval(true);
    }

    public function testAttributeRejectsPolicyOnParentReference(): void
    {
        $attribute = new OrmAttribute\HasOne(
            LifecycleParent::class,
            'parent',
            ['parent_id' => 'id'],
            orphanRemoval: true,
        );
        $this->expectException(RelationException::class);
        $attribute->init($this->orm->getMapper(LifecycleChild::class)->getRelationships());
    }

    public function testProgrammaticPolicyAndDirectLinkNative(): void
    {
        $parent = LifecycleParent::find(1);
        $relation = new OneToMany(
            'children',
            LifecycleParent::class,
            LifecycleChild::class,
            ['id' => 'parent_id'],
            false,
        );
        $child = LifecycleChild::find(10);
        $collection = new Collection([$child]);
        unset($collection[0]);
        $relation->linkNative($parent, $collection);
        $this->assertNull($this->row(10)['parent_id']);
        $declared = $this->orm->getMapper($parent)->getRelationships()->hasMany(
            LifecycleChild::class,
            'programmatic',
            ['id' => 'parent_id'],
            orphanRemoval: false,
        );
        $this->assertFalse($declared->getOrphanRemoval());
    }

    private function row(int $id): ?array
    {
        return $this->connection->fetchOne('SELECT * FROM lifecycle_child WHERE id = ?', [$id]);
    }

    public function testCompositeDetachmentClearsAllColumnsAndKeepsOtherParent(): void
    {
        $parent = LifecycleCompositeParent::query()->whereEquals(['tenant' => 'a', 'ref' => 0])->get();
        // This test exercises composite detachment, independently of tuple-IN
        // support in the SQLite library bundled with older PHP distributions.
        $parent->getRelated()->setLoaded('children', new Collection([LifecycleCompositeChild::find(1)]));
        $children = $parent->children;
        $this->assertCount(1, $children);
        unset($children[0]);
        $parent->save();
        $row = $this->connection->fetchOne('SELECT * FROM lifecycle_composite_child WHERE id = 1');
        $this->assertNotNull($row);
        $this->assertSame(1, (int)$row['id']);
        $this->assertNull($row['tenant']);
        $this->assertNull($row['parent_ref']);
        $this->assertSame(
            'b',
            $this->connection->fetchOne('SELECT * FROM lifecycle_composite_child WHERE id = 2')['tenant'],
        );
    }

    public function testPivotDetachmentPreservesTargetAndOtherLinks(): void
    {
        $parent = LifecycleParent::find(1);
        $tags = $parent->tags;
        unset($tags[0]);
        $parent->save();
        $this->assertNotNull($this->connection->fetchOne('SELECT * FROM lifecycle_tag WHERE id = 1'));
        $this->assertNull($this->connection->fetchOne('SELECT * FROM lifecycle_link WHERE parent_id = 1'));
        $this->assertNotNull($this->connection->fetchOne('SELECT * FROM lifecycle_link WHERE parent_id = 2'));
    }

    public function testDestructivePolicyIsRejectedOnPivotRelationship(): void
    {
        $relation = $this->orm->getMapper(LifecycleParent::class)->getRelationships()->get('tags');
        $this->expectException(RelationException::class);
        $relation->setOrphanRemoval(true);
    }

    public function testVetoedRemovalRollsBackAndRetainsTracking(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $child = $children->first();
        unset($children[0]);
        $this->orm->setEventDispatcher(new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof EntityBeforeDeleteEvent) {
                    $event->stopPropagation();
                }
                return $event;
            }
        });
        try {
            $parent->save();
            $this->fail('Veto must abort lifecycle removal');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('prevented', $exception->getMessage());
        }
        $this->assertNotNull($this->row(10));
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($child));
        $this->assertCount(1, iterator_to_array($children->detached()));
    }

    public function testVetoedChildSaveDoesNotCommitOldChildRemoval(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $replacement = new LifecycleChild();
        $replacement->code = 'a';
        $children[0] = $replacement;
        $this->orm->setEventDispatcher(new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof EntityBeforeSaveEvent && $event->getEntity() instanceof LifecycleChild) {
                    $event->stopPropagation();
                }
                return $event;
            }
        });
        try {
            $parent->save();
            $this->fail('Veto must abort lifecycle replacement');
        } catch (RelationException $exception) {
            $this->assertStringContainsString('prevented', $exception->getMessage());
        }
        $this->assertNotNull($this->row(10));
        $this->assertNull($replacement->parent_id);
    }

    public function testFailedInsertRestoresUninitializedTypedIdentifier(): void
    {
        $parent = LifecycleParent::find(1);
        $first = new LifecycleUninitializedChild();
        $first->code = 'c';
        $second = new LifecycleUninitializedChild();
        $second->code = 'invalid';
        $parent->uninitialized = new Collection([$first, $second]);
        try {
            $parent->save();
            $this->fail('Invalid child must fail');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('CHECK', $exception->getMessage());
        }
        $this->assertFalse((new ReflectionProperty($first, 'id'))->isInitialized($first));
        $this->assertNull($this->orm->getStatus($first));
        $this->assertNull($first->parent_id);
    }

    public function testRemovedQueuedTransientChildIsNotInsertedByBatch(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $child = new LifecycleChild();
        $child->code = 'queued';
        $children[] = $child;
        unset($children[2]);
        $this->orm->save($parent);
        $this->orm->save($child);
        $this->orm->persist();
        $this->assertNull($child->id);
        $this->assertNull($this->orm->getStatus($child));
    }

    public function testCrossConnectionGraphIsRejectedBeforeParentWrite(): void
    {
        $parent = new LifecycleParent();
        $parent->name = 'cross';
        $parent->remote = new Collection([new LifecycleRemoteChild()]);
        $this->expectException(OrmException::class);
        $this->expectExceptionMessage('cannot span multiple connections');
        try {
            $parent->save();
        } finally {
            $this->assertNull($parent->id);
            $this->assertCount(2, $this->connection->fetchAll('SELECT * FROM lifecycle_parent'));
        }
    }

    public function testBatchPersistsExplicitlyScheduledChildrenDuringLinking(): void
    {
        $parent = new LifecycleParent();
        $parent->name = 'batch';
        $child = new LifecycleChild();
        $child->code = 'batch';
        $parent->owned = new Collection([$child]);
        $this->orm->save($parent);
        $this->orm->save($child);
        $this->orm->persist();
        $this->assertNotNull($child->id);
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame(EntityStorage::STATUS_NONE, $this->orm->getStatus($child));
    }

    public function testOrphanRemovalSupersedesPendingChildUpdate(): void
    {
        $parent = LifecycleParent::find(1);
        $children = $parent->owned;
        $child = $children->first();
        $child->code = 'pending';
        unset($children[0]);
        $this->orm->save($parent);
        $this->orm->save($child);
        $this->orm->persist();
        $this->assertNull($this->row(10));
        $this->assertNull($this->orm->getStatus($child));
    }

    public function testLifecycleServiceSharesNestedContextAndReturnsOperationResult(): void
    {
        $parent = new LifecycleParent();
        $parent->name = 'service';
        $lifecycle = $this->orm->lifecycle();

        $result = $lifecycle->transaction($parent, function () use ($parent, $lifecycle): int {
            $this->assertSame($lifecycle, $this->orm->lifecycle());
            $this->assertTrue($lifecycle->isActive());

            return $lifecycle->transaction($parent, function () use ($parent): int {
                $parent->save();

                return $parent->id;
            });
        });

        $this->assertSame($parent->id, $result);
        $this->assertFalse($lifecycle->isActive());
        $this->assertFalse($this->connection->inTransaction());
        $this->assertCount(3, $this->connection->fetchAll('SELECT * FROM lifecycle_parent'));
    }

    public function testLifecycleServiceRestoresNestedWritesAndResetsContextAfterFailure(): void
    {
        $parent = new LifecycleParent();
        $parent->name = 'service';
        $lifecycle = $this->orm->lifecycle();

        try {
            $lifecycle->transaction($parent, function () use ($parent, $lifecycle): void {
                $lifecycle->transaction($parent, function () use ($parent): void {
                    $parent->save();
                });

                throw new RuntimeException('Abort outer lifecycle operation');
            });
            $this->fail('The outer operation must fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Abort outer lifecycle operation', $exception->getMessage());
        }

        $this->assertFalse($lifecycle->isActive());
        $this->assertFalse($this->connection->inTransaction());
        $this->assertNull($parent->id);
        $this->assertNull($this->orm->getStatus($parent));
        $this->assertCount(2, $this->connection->fetchAll('SELECT * FROM lifecycle_parent'));

        $lifecycle->transaction($parent, function () use ($parent): void {
            $parent->save();
        });
        $this->assertNotNull($parent->id);
    }
}

#[OrmAttribute\Table('lifecycle_parent', 'main')]
#[OrmAttribute\HasMany(LifecycleChild::class, 'children', ['id' => 'parent_id'], orphanRemoval: false)]
#[OrmAttribute\HasMany(LifecycleChild::class, 'owned', ['id' => 'parent_id'], orphanRemoval: true)]
#[OrmAttribute\HasMany(LifecycleChild::class, 'legacy', ['id' => 'parent_id'])]
#[OrmAttribute\HasMany(
    LifecycleChild::class,
    'filtered',
    ['id' => 'parent_id'],
    orphanRemoval: true,
    where: ['code' => 'a'],
)]
#[OrmAttribute\HasMany(LifecycleRequiredChild::class, 'required', ['id' => 'parent_id'], orphanRemoval: false)]
#[OrmAttribute\HasMany(LifecycleUninitializedChild::class, 'uninitialized', ['id' => 'parent_id'], orphanRemoval: true)]
#[OrmAttribute\HasMany(LifecycleRemoteChild::class, 'remote', ['id' => 'parent_id'], orphanRemoval: true)]
#[OrmAttribute\BelongsToMany(LifecycleTag::class, 'tags', 'lifecycle_link', ['id' => 'parent_id'], ['tag_id' => 'id'])]
class LifecycleParent extends MagicEntity
{
}

#[OrmAttribute\Table('lifecycle_child', 'main')]
#[OrmAttribute\HasOne(LifecycleParent::class, 'parent', ['parent_id' => 'id'])]
class LifecycleChild extends Entity
{
    public ?int $id = null;
    public ?int $parent_id = null;
    public string $code;

    public function __get(string $name): mixed
    {
        return $this->getRelated()->get($name);
    }
}

#[OrmAttribute\Table('lifecycle_required', 'main')]
class LifecycleRequiredChild extends MagicEntity
{
}

#[OrmAttribute\Table('lifecycle_child', 'main')]
class LifecycleUninitializedChild extends Entity
{
    public int $id;
    public ?int $parent_id = null;
    public string $code;
}

#[OrmAttribute\Table('lifecycle_child', 'main', connection: 'other')]
class LifecycleRemoteChild extends MagicEntity
{
}

#[OrmAttribute\Table('lifecycle_composite_parent', 'main')]
#[OrmAttribute\HasMany(
    LifecycleCompositeChild::class,
    'children',
    ['tenant' => 'tenant', 'ref' => 'parent_ref'],
    orphanRemoval: false,
)]
class LifecycleCompositeParent extends MagicEntity
{
}

#[OrmAttribute\Table('lifecycle_composite_child', 'main')]
class LifecycleCompositeChild extends MagicEntity
{
}

#[OrmAttribute\Table('lifecycle_tag', 'main')]
class LifecycleTag extends MagicEntity
{
}
