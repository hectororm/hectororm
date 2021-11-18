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

namespace Hector\Orm\Tests\Mapper;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Mapper\AbstractMapper;
use Hector\Orm\Mapper\GenericMapper;
use Hector\Orm\Mapper\MagicMapper;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use Hector\Orm\Tests\Fake\Entity\LanguageCollection;
use PDOException;
use stdClass;
use TypeError;

class AbstractMapperTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $mapper = new FakeAbstractMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertInstanceOf(AbstractMapper::class, $mapper);
        $this->assertInstanceOf(FakeAbstractMapper::class, $mapper);
    }

    public function testConstructWithNotAnEntity()
    {
        $this->expectException(TypeError::class);

        new FakeAbstractMapper(stdClass::class, $this->getOrm()->getStorage());
    }

    public function testGetRelationships()
    {
        $mapper = new FakeAbstractMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertInstanceOf(Relationships::class, $mapper->getRelationships());
        $this->assertSame($mapper->getRelationships(), $mapper->getRelationships());

        $this->assertCount(3, $mapper->getRelationships());
    }

    public function testInsertEntity()
    {
        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertNull($entity->film_id);

        $nbAffected = $mapper->insertEntity($entity);

        $this->assertEquals(1, $nbAffected);
        $this->assertNotNull($entity->film_id);
    }

    public function testInsertExistentEntity()
    {
        $this->expectException(PDOException::class);

        $entity = Film::get(1);
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $mapper->insertEntity($entity);
    }

    public function testUpdateEntity()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;
        $mapper->insertEntity($entity);

        $entity->title = 'Qux';

        $nbAffected = $mapper->updateEntity($entity);
        $this->assertEquals(1, $nbAffected);
    }

    public function testUpdateEntityNew()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);

        $mapper->updateEntity($entity);
    }

    public function testDeleteEntity()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;
        $mapper->insertEntity($entity);

        $nbAffected = $mapper->deleteEntity($entity);
        $this->assertEquals(1, $nbAffected);
    }

    public function testDeleteEntityNew()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);

        $mapper->deleteEntity($entity);
    }

    public function testDeleteEntityNonexistent()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;
        $mapper->insertEntity($entity);

        $nbAffected = $mapper->deleteEntity($entity);

        $this->assertEquals(1, $nbAffected);
    }

    public function testDeleteEntityNoPrimaryValue()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);
        $mapper->deleteEntity($entity);
    }

    public function testRefreshEntity()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = Film::get(1);

        $entity->title = 'Foo';

        $mapper->refreshEntity($entity);

        $this->assertNotEquals('Foo', $entity->title);
    }

    public function testRefreshEntityNew()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';

        $this->expectException(MapperException::class);

        $mapper->refreshEntity($entity);
    }

    public function testRefreshEntityDeleted()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $mapper->insertEntity($entity);
        $mapper->deleteEntity($entity);

        $this->expectException(MapperException::class);

        $mapper->refreshEntity($entity);
    }

    public function testFetchWithPersonalizedCollection()
    {
        $mapper = new GenericMapper(Language::class, $this->getOrm()->getStorage());
        $collection = $mapper->fetchAllWithBuilder(
            (new Builder(Language::class))->where('language_id', [1, 2])
        );

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertInstanceOf(LanguageCollection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testGetEntityAlteration_freshEntity()
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());
        $entity = $mapper->fetchOneWithBuilder((new Builder(Language::class))->where('language_id', 2));

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertEquals([], $mapper->getEntityAlteration($entity));
    }

    public function testGetEntityAlteration_alteredEntity()
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());
        /** @var Language $entity */
        $entity = $mapper->fetchOneWithBuilder((new Builder(Language::class))->where('language_id', 2));

        $this->assertInstanceOf(Entity::class, $entity);

        $entity->name = 'FOO';

        $this->assertEquals(['name'], $mapper->getEntityAlteration($entity));
        $this->assertEquals([], $mapper->getEntityAlteration($entity, ['language_id']));
        $this->assertEquals(['name'], $mapper->getEntityAlteration($entity, ['name']));
        $this->assertEquals(['name'], $mapper->getEntityAlteration($entity, ['language_id', 'name']));
    }

    public function testGetEntityAlteration_newEntity()
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());
        $entity = new Language();

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertEquals(['language_id', 'name', 'last_update'], $mapper->getEntityAlteration($entity));
        $this->assertEquals(['name'], $mapper->getEntityAlteration($entity, ['name']));
    }
}
