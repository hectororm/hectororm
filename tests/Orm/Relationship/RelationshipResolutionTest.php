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
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Orm;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
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
