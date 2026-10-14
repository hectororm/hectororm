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
