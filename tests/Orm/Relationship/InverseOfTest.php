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

use ArgumentCountError;
use Hector\Connection\Connection;
use Hector\Orm\Attributes as Mapping;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\ManyToMany;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Relationship\OneToOne;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class InverseOfTest extends TestCase
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
        $connection->execute('CREATE TABLE inverse_parent (id INTEGER PRIMARY KEY, tenant INTEGER)');
        $connection->execute(
            'CREATE TABLE inverse_child (id INTEGER PRIMARY KEY, tenant INTEGER, '
            . 'parent_id INTEGER REFERENCES inverse_parent(id), parent_tenant INTEGER)',
        );
        $connection->execute('CREATE TABLE inverse_pivot (parent_key INTEGER, child_key INTEGER)');
        $this->orm = new Orm($connection, (new Sqlite($connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    /** @dataProvider structuralProvider */
    public function testStructuralInversion(string $type, bool $attribute): void
    {
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $columns = ['id' => 'parent_id', 'tenant' => 'parent_tenant'];
        $foreign = match ($type) {
            'many' => $parents->hasMany(InverseChild::class, 'original', $columns),
            'one' => $parents->hasOne(InverseChild::class, 'original', $columns),
            'parent' => $parents->hasOneChild(InverseChild::class, 'original', $columns),
            'dependent' => $parents->oneToOne(InverseChild::class, 'original', $columns, isParent: false),
            'inferred' => $parents->oneToOne(InverseChild::class, 'original', ['id' => 'parent_id']),
            'fallback' => $parents->oneToOne(InverseChild::class, 'original', ['id' => 'id']),
            'pivot' => $parents->belongsToMany(
                InverseChild::class,
                'original',
                'inverse_pivot',
                ['id' => 'parent_key'],
                ['child_key' => 'id'],
            ),
        };

        if ($attribute) {
            $children->addDeclaration(new Mapping\InverseOf(InverseParent::class, 'inverse', 'original'));
            $inverse = $children->get('inverse');
        } else {
            $inverse = $children->inverseOf(InverseParent::class, 'inverse', 'original');
        }

        $expected = match ($type) {
            'many' => ManyToOne::class,
            'one' => OneToMany::class,
            'pivot' => ManyToMany::class,
            default => OneToOne::class,
        };
        $this->assertInstanceOf($expected, $inverse);
        $this->assertSame('inverse', $inverse->getName());
        $this->assertSame(InverseChild::class, $inverse->getSourceEntity());
        $this->assertSame(InverseParent::class, $inverse->getTargetEntity());
        $this->assertSame($foreign->getTargetColumns(), $inverse->getSourceColumns());
        $this->assertSame($foreign->getSourceColumns(), $inverse->getTargetColumns());
        $this->assertSame($inverse, $children->get('inverse'));

        if ($inverse instanceof OneToOne) {
            $this->assertSame(
                null === $foreign->isParent() ? null : !$foreign->isParent(),
                $inverse->isParent(),
            );
        }
        if ($inverse instanceof ManyToMany) {
            $this->assertSame($foreign->getPivotTable(), $inverse->getPivotTable());
            $this->assertSame($foreign->getPivotSourceColumns(), $inverse->getPivotTargetColumns());
            $this->assertSame($foreign->getPivotTargetColumns(), $inverse->getPivotSourceColumns());
        }

        $double = $parents->inverseOf(InverseChild::class, 'double', 'inverse');
        $this->assertSame($foreign::class, $double::class);
        $this->assertSame($foreign->getSourceColumns(), $double->getSourceColumns());
        $this->assertSame($foreign->getTargetColumns(), $double->getTargetColumns());
    }

    public function structuralProvider(): iterable
    {
        foreach (['many', 'one', 'parent', 'dependent', 'inferred', 'fallback', 'pivot'] as $type) {
            yield $type . ' programmatic' => [$type, false];
            yield $type . ' attribute' => [$type, true];
        }
    }

    public function testAttributeDiscoveryResolvesForwardReference(): void
    {
        $relations = $this->orm->getMapper(InverseCategory::class)->getRelationships();
        $this->assertInstanceOf(OneToMany::class, $relations->get('children'));
        $this->assertSame(['parent_id'], $relations->get('children')->getTargetColumns());
    }

    public function testCycleIsReportedAndRetryable(): void
    {
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $parents->addDeclaration(new Mapping\InverseOf(InverseChild::class, 'children', 'parent'));
        $children->addDeclaration(new Mapping\InverseOf(InverseParent::class, 'parent', 'children'));
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $parents->get('children');
                $this->fail('A cycle must fail on every lookup');
            } catch (RelationException $exception) {
                $this->assertSame(
                    'Circular relationship dependency: ' . InverseParent::class . '.children -> '
                    . InverseChild::class . '.parent -> ' . InverseParent::class . '.children',
                    $exception->getMessage(),
                );
            }
        }
    }

    public function testUnknownReference(): void
    {
        $relations = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship "missing" not found');
        $relations->inverseOf(InverseParent::class, 'parent', 'missing');
    }

    public function testReferenceMustTargetCurrentEntity(): void
    {
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $parents->hasOne(InverseParent::class, 'wrong', ['id' => 'id']);
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Relationship not found between');
        $this->orm->getMapper(InverseChild::class)->getRelationships()->inverseOf(
            InverseParent::class,
            'parent',
            'wrong',
        );
    }

    /** @dataProvider apiProvider */
    public function testForeignNameIsRequired(bool $attribute): void
    {
        $this->expectException(ArgumentCountError::class);
        if ($attribute) {
            new Mapping\InverseOf(InverseParent::class, 'parent');

            return;
        }
        $this->orm->getMapper(InverseChild::class)->getRelationships()->inverseOf(InverseParent::class, 'parent');
    }

    public function apiProvider(): array
    {
        return [[false], [true]];
    }

    public function testNamedReferenceDisambiguatesRelations(): void
    {
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $parents->hasMany(InverseChild::class, 'first', ['id' => 'parent_id']);
        $parents->hasMany(InverseChild::class, 'second', ['tenant' => 'parent_tenant']);
        $inverse = $this->orm->getMapper(InverseChild::class)->getRelationships()->inverseOf(
            InverseParent::class,
            'parent',
            'second',
        );
        $this->assertSame(['parent_tenant'], $inverse->getSourceColumns());
    }

    /** @dataProvider customProvider */
    public function testCustomReverseIsUsedAndValidated(string $case): void
    {
        $source = 'source' === $case ? InverseChild::class : InverseParent::class;
        $foreign = $this->getMockBuilder(ManyToOne::class)
            ->setConstructorArgs(['original', $source, InverseChild::class, ['id' => 'id']])
            ->onlyMethods(['reverse'])
            ->getMock();
        $inverse = new OneToMany(
            'name' === $case ? 'wrong' : 'inverse',
            'endpoints' === $case ? InverseParent::class : InverseChild::class,
            InverseParent::class,
            ['id' => 'id'],
        );
        $foreign->expects('source' === $case ? $this->never() : $this->once())
            ->method('reverse')->with('inverse')->willReturn('same' === $case ? $foreign : $inverse);
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $parents->addDeclaration(new InverseFixtureAttribute($foreign));
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        if ('valid' !== $case) {
            $this->expectException(RelationException::class);
            $this->expectExceptionMessage('source' === $case ? 'must originate from' : 'invalid inverse');
        }
        $this->assertSame($inverse, $children->inverseOf(InverseParent::class, 'inverse', 'original'));
    }

    public function customProvider(): iterable
    {
        foreach (['valid', 'source', 'name', 'endpoints', 'same'] as $case) {
            yield $case => [$case];
        }
    }

    public function testQueryClausesAreIndependentEvenWhenCustomReverseCopiesThem(): void
    {
        $foreign = $this->getMockBuilder(ManyToOne::class)
            ->setConstructorArgs(['original', InverseParent::class, InverseChild::class, ['id' => 'id']])
            ->onlyMethods(['reverse'])->getMock();
        $foreign->whereEquals(['tenant' => 1])->groupBy('tenant')->havingEquals(['tenant' => 1])
            ->orderBy('tenant')->limit(3, 2);
        $inverse = new OneToMany('inverse', InverseChild::class, InverseParent::class, ['id' => 'id'], true);
        // A custom reverse may shallow-copy clause objects; resetting must not mutate the original.
        foreach (['where', 'group', 'having', 'order', 'limit'] as $clause) {
            $inverse->{$clause} = $foreign->{$clause};
        }
        $foreign->method('reverse')->willReturn($inverse);
        $this->orm->getMapper(InverseParent::class)->getRelationships()
            ->addDeclaration(new InverseFixtureAttribute($foreign));
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $children->addDeclaration(new Mapping\InverseOf(
            InverseParent::class,
            'inverse',
            'original',
            where: ['id' => 7],
            groupBy: ['id'],
            having: ['id' => 7],
            orderBy: ['id' => 'DESC'],
            limit: [10, 4],
        ));
        $resolved = $children->get('inverse');
        foreach (['where', 'group', 'having', 'order'] as $clause) {
            $this->assertNotSame($foreign->{$clause}, $resolved->{$clause});
            $this->assertCount(1, $foreign->{$clause});
            $this->assertCount(1, $resolved->{$clause});
        }
        $this->assertSame(3, $foreign->limit->getLimit());
        $this->assertSame(2, $foreign->limit->getOffset());
        $this->assertSame(10, $resolved->limit->getLimit());
        $this->assertSame(4, $resolved->limit->getOffset());
        $this->assertFalse($resolved->getOrphanRemoval());
    }

    /** @dataProvider filteredReadProvider */
    public function testOppositeFilterIsRespectedRegardlessOfLoadingOrder(bool $eager, bool $inverseFirst): void
    {
        $this->orm->getConnection()->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $this->orm->getConnection()->execute('INSERT INTO inverse_child VALUES (2, 20, 1, NULL)');
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $parents->hasMany(InverseChild::class, 'children', ['id' => 'parent_id']);
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $children->addDeclaration(new Mapping\InverseOf(
            InverseParent::class, 'parent', 'children', where: ['tenant' => 999],
        ));
        $parent = InverseParent::find(1);
        if ($inverseFirst) {
            $this->assertNull(InverseChild::find(2)->parent);
        }
        if ($eager) {
            (new Collection([$parent]))->load(['children']);
        }
        $this->assertCount(1, $parent->children);
        $this->assertNull($parent->children->first()->parent);
    }

    public function filteredReadProvider(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    /** @dataProvider apiProvider */
    public function testInverseCollectionDetachesByDefaultAndDeletesOnlyExplicitly(bool $orphanRemoval): void
    {
        $this->orm->getConnection()->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $this->orm->getConnection()->execute('INSERT INTO inverse_child VALUES (2, 20, 1, NULL)');
        $this->orm->getMapper(InverseChild::class)->getRelationships()
            ->hasOne(InverseParent::class, 'parent', ['parent_id' => 'id']);
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $attribute = $orphanRemoval
            ? new Mapping\InverseOf(InverseChild::class, 'children', 'parent', orphanRemoval: true)
            : new Mapping\InverseOf(InverseChild::class, 'children', 'parent');
        $parents->addDeclaration($attribute);
        $this->assertSame($orphanRemoval, $parents->get('children')->getOrphanRemoval());
        $parent = InverseParent::find(1);
        $collection = $parent->children;
        unset($collection[0]);
        $parent->save();
        $rows = $this->orm->getConnection()->fetchAll('SELECT * FROM inverse_child');
        $this->assertCount($orphanRemoval ? 0 : 1, $rows);
        if (!$orphanRemoval) {
            $this->assertNull($rows[0]['parent_id']);
        }
        $this->assertCount(1, $this->orm->getConnection()->fetchAll('SELECT * FROM inverse_parent'));
    }

    public function testParentReferenceRejectsLifecycleOption(): void
    {
        $this->orm->getMapper(InverseParent::class)->getRelationships()
            ->hasOneChild(InverseChild::class, 'child', ['id' => 'parent_id'], orphanRemoval: true);
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $children->addDeclaration(new Mapping\InverseOf(
            InverseParent::class, 'parent', 'child', orphanRemoval: true,
        ));
        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('does not support orphanRemoval');
        $children->get('parent');
    }

    public function testFiltersAreNotAssignedToNewChildren(): void
    {
        $this->orm->getConnection()->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $this->orm->getMapper(InverseChild::class)->getRelationships()
            ->hasOne(InverseParent::class, 'parent', ['parent_id' => 'id']);
        $this->orm->getMapper(InverseParent::class)->getRelationships()->addDeclaration(new Mapping\InverseOf(
            InverseChild::class, 'children', 'parent', where: ['tenant' => 999],
        ));
        $parent = InverseParent::find(1);
        $child = new InverseChild();
        $child->id = 2;
        $child->tenant = 42;
        $parent->children = new Collection([$child]);
        $parent->save();
        $row = $this->orm->getConnection()->fetchAll('SELECT * FROM inverse_child')[0];
        $this->assertEquals(42, $row['tenant']);
        $this->assertEquals(1, $row['parent_id']);
    }

    public function testCollectionOnOppositeSideIsNotPreloadedAsScalar(): void
    {
        $this->orm->getConnection()->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $this->orm->getConnection()->execute('INSERT INTO inverse_child VALUES (2, 20, 1, NULL)');
        $this->orm->getMapper(InverseParent::class)->getRelationships()
            ->hasMany(InverseChild::class, 'children', ['id' => 'parent_id']);
        $this->orm->getMapper(InverseChild::class)->getRelationships()
            ->hasMany(InverseParent::class, 'parents', ['parent_id' => 'id']);
        $child = InverseParent::find(1)->children->first();
        $this->assertInstanceOf(Collection::class, $child->parents);
        $this->assertCount(1, $child->parents);
    }

    public function testInversePivotDetachmentPreservesTargetEntity(): void
    {
        $connection = $this->orm->getConnection();
        $connection->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $connection->execute('INSERT INTO inverse_child VALUES (2, 20, NULL, NULL)');
        $connection->execute('INSERT INTO inverse_pivot VALUES (1, 2)');
        $this->orm->getMapper(InverseParent::class)->getRelationships()->belongsToMany(
            InverseChild::class, 'children', 'inverse_pivot', ['id' => 'parent_key'], ['child_key' => 'id'],
        );
        $this->orm->getMapper(InverseChild::class)->getRelationships()
            ->inverseOf(InverseParent::class, 'parents', 'children');
        $child = InverseChild::find(2);
        $parents = $child->parents;
        unset($parents[0]);
        $child->save();
        $this->assertCount(0, $connection->fetchAll('SELECT * FROM inverse_pivot'));
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM inverse_parent'));
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM inverse_child'));
    }

    /** @dataProvider belongsToProvider */
    public function testBelongsToDiagnosesOnlyNonParentReferences(string $type, bool $attribute): void
    {
        $parents = $this->orm->getMapper(InverseParent::class)->getRelationships();
        $children = $this->orm->getMapper(InverseChild::class)->getRelationships();
        $columns = ['id' => 'parent_id'];
        match ($type) {
            'many' => $parents->hasMany(InverseChild::class, 'original', $columns),
            'one' => $parents->hasOne(InverseChild::class, 'original', $columns),
            'parent' => $parents->hasOneChild(InverseChild::class, 'original', $columns),
            'dependent' => $parents->oneToOne(InverseChild::class, 'original', $columns, isParent: false),
            'inferred' => $parents->oneToOne(InverseChild::class, 'original', $columns),
            'fallback' => $parents->oneToOne(InverseChild::class, 'original', ['id' => 'id']),
            'pivot' => $parents->belongsToMany(
                InverseChild::class, 'original', 'inverse_pivot', ['id' => 'parent_key'], ['child_key' => 'id'],
            ),
        };
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                if ($attribute) {
                    if (0 === $attempt) {
                        $children->addDeclaration(new Mapping\BelongsTo(InverseParent::class, 'inverse', 'original'));
                    }
                    $inverse = $children->get('inverse');
                } else {
                    // Omitting foreignName remains supported for unambiguous legacy declarations.
                    $inverse = $children->belongsTo(InverseParent::class, 'inverse');
                }
                $this->assertSame($inverse, $children->get('inverse'));
            }
        } finally {
            restore_error_handler();
        }

        $deprecated = in_array($type, ['one', 'dependent', 'pivot'], true);
        $this->assertCount($deprecated ? 1 : 0, $warnings);
        if ($deprecated) {
            $this->assertStringContainsString(InverseChild::class . '.inverse', $warnings[0]);
            $this->assertStringContainsString('InverseOf(', $warnings[0]);
            $this->assertStringContainsString('foreignName: "original"', $warnings[0]);
        }
        if ('one' === $type) {
            $this->assertInstanceOf(OneToMany::class, $inverse);
            $this->assertNull($inverse->getOrphanRemoval());
        }
        if (in_array($type, ['parent', 'inferred', 'dependent', 'fallback'], true)) {
            $this->assertInstanceOf(OneToOne::class, $inverse);
            $this->assertSame(match ($type) {
                'dependent' => true,
                'fallback' => null,
                default => false,
            }, $inverse->isParent());
        }
    }

    public function belongsToProvider(): iterable
    {
        yield from $this->structuralProvider();
    }

    public function testLegacyCollectionKeepsDeletionBehaviorWithoutWarningsOnEveryAccessOrSave(): void
    {
        $connection = $this->orm->getConnection();
        $connection->execute('INSERT INTO inverse_parent VALUES (1, 10)');
        $connection->execute('INSERT INTO inverse_child VALUES (2, 20, 1, NULL)');
        $this->orm->getMapper(InverseChild::class)->getRelationships()
            ->hasOne(InverseParent::class, 'parent', ['parent_id' => 'id']);
        $this->orm->getMapper(InverseParent::class)->getRelationships()
            ->addDeclaration(new Mapping\BelongsTo(InverseChild::class, 'children', 'parent'));
        $warnings = 0;
        set_error_handler(static function () use (&$warnings): bool {
            $warnings++;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $parent = InverseParent::find(1);
            $collection = $parent->children;
            $this->assertSame($collection, $parent->children);
            $parent->save();
            unset($collection[0]);
            $parent->save();
            $parent->save();
        } finally {
            restore_error_handler();
        }
        $this->assertSame(1, $warnings);
        $this->assertCount(0, $connection->fetchAll('SELECT * FROM inverse_child'));
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM inverse_parent'));
    }
}

#[Mapping\Table('inverse_parent')]
class InverseParent extends MagicEntity
{
}

#[Mapping\Table('inverse_child')]
class InverseChild extends MagicEntity
{
}

#[Mapping\Table('inverse_child')]
#[Mapping\InverseOf(InverseCategory::class, 'children', 'parent')]
#[Mapping\HasOne(InverseCategory::class, 'parent', ['parent_id' => 'id'])]
class InverseCategory extends MagicEntity
{
}

class InverseFixtureAttribute extends Mapping\RelationshipAttribute
{
    public function __construct(private Relationship $relationship)
    {
        parent::__construct($relationship->getTargetEntity(), $relationship->getName());
    }

    protected function addRelationship(Relationships $relationships): Relationship
    {
        return $this->relationship;
    }
}
