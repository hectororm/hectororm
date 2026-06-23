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
use PDOException;
use stdClass;
use TypeError;

class AbstractMapperTest extends AbstractTestCase
{
    public function testConstruct(): void
    {
        $mapper = new FakeAbstractMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertInstanceOf(AbstractMapper::class, $mapper);
        $this->assertInstanceOf(FakeAbstractMapper::class, $mapper);
    }

    public function testConstructWithNotAnEntity(): void
    {
        $this->expectException(TypeError::class);

        new FakeAbstractMapper(stdClass::class, $this->getOrm()->getStorage());
    }

    public function testGetRelationships(): void
    {
        $mapper = new FakeAbstractMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertInstanceOf(Relationships::class, $mapper->getRelationships());
        $this->assertSame($mapper->getRelationships(), $mapper->getRelationships());

        $this->assertCount(3, $mapper->getRelationships());
    }

    public function testInsertEntity(): void
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

    public function testInsertExistentEntity(): void
    {
        $this->expectException(PDOException::class);

        $entity = Film::get(1);
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $mapper->insertEntity($entity);
    }

    public function testUpdateEntity(): void
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

    public function testUpdateEntityNew(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);

        $mapper->updateEntity($entity);
    }

    public function testUpdateEntityWithMutatedPrimaryKey(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = Film::get(1);
        $originalId = $entity->film_id;
        $entity->title = 'Qux';
        $entity->film_id = 999999;

        $this->expectException(MapperException::class);

        try {
            $mapper->updateEntity($entity);
        } finally {
            // The mutated primary key must not have reached the database.
            $count = $this->getOrm()->getConnection()->fetchColumn(
                'SELECT COUNT(*) FROM film WHERE film_id = ?',
                [999999]
            );
            $this->assertSame(0, (int)($count[0] ?? 0));
            $this->assertNotSame(999999, $originalId);
        }
    }

    public function testDeleteEntity(): void
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

    public function testDeleteEntityNew(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);

        $mapper->deleteEntity($entity);
    }

    public function testDeleteEntityNonexistent(): void
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

    public function testDeleteEntityNoPrimaryValue(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $entity->language_id = 1;

        $this->expectException(MapperException::class);
        $mapper->deleteEntity($entity);
    }

    public function testRefreshEntity(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = Film::get(1);

        $entity->title = 'Foo';

        $mapper->refreshEntity($entity);

        $this->assertNotEquals('Foo', $entity->title);
    }

    public function testRefreshEntityNew(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $entity = new Film();
        $entity->title = 'Foo';
        $entity->description = 'Bar';

        $this->expectException(MapperException::class);

        $mapper->refreshEntity($entity);
    }

    public function testRefreshEntityDeleted(): void
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

    public function testGetEntityAlteration_freshEntity(): void
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());
        $entity = $mapper->fetchOneWithBuilder((new Builder(Language::class))->where('language_id', 2));

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertEquals([], $mapper->getEntityAlteration($entity));
    }

    public function testGetEntityAlteration_alteredEntity(): void
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

    public function testGetEntityAlteration_newEntity(): void
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());
        $entity = new Language();

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertEquals(['language_id', 'name', 'last_update'], $mapper->getEntityAlteration($entity));
        $this->assertEquals(['name'], $mapper->getEntityAlteration($entity, ['name']));
    }

    public function testGetEntityAlteration_partialOriginalDoesNotEmitWarning(): void
    {
        $mapper = new MagicMapper(Language::class, $this->getOrm()->getStorage());

        // Fetch only the primary key column: the stored "original" data will not
        // contain "name" nor "last_update", which must not trigger an
        // "Undefined array key" warning when computing the alteration.
        $builder = (new Builder(Language::class))->where('language_id', 2);
        $builder->resetColumns()->column('language_id');

        /** @var Language $entity */
        $entity = $mapper->fetchOneWithBuilder($builder);
        $this->assertInstanceOf(Entity::class, $entity);

        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;

            return true;
        });

        try {
            $alteration = $mapper->getEntityAlteration($entity);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors, 'getEntityAlteration must not emit PHP warnings on partial original data');

        // Columns missing from the original data cannot be proven unchanged, so they
        // are reported as altered; the loaded primary key is unchanged.
        $this->assertNotContains('language_id', $alteration);
        $this->assertContains('name', $alteration);
        $this->assertContains('last_update', $alteration);
    }

    public function testGetEntityAlteration_unchangedAcrossColumnTypes(): void
    {
        // The "film" table exercises a wide range of column types (smallint, year,
        // decimal, enum, set, timestamp...). A freshly loaded, untouched entity must
        // report no alteration: the typed equals() comparison alone is enough, with
        // no loose `!=` fallback needed.
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        /** @var Film $entity */
        $entity = $mapper->fetchOneWithBuilder((new Builder(Film::class))->where('film_id', 1));

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertSame([], $mapper->getEntityAlteration($entity));
    }

    public function testGetEntityAlteration_detectsChangesAcrossColumnTypes(): void
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        /** @var Film $entity */
        $entity = $mapper->fetchOneWithBuilder((new Builder(Film::class))->where('film_id', 1));

        $this->assertInstanceOf(Entity::class, $entity);

        // decimal column (?float)
        $entity->rental_rate += 1.0;
        // enum column (?string)
        $entity->rating = 'R' === $entity->rating ? 'PG' : 'R';
        // set column (?array)
        $entity->special_features = ['Trailers'];

        $alteration = $mapper->getEntityAlteration($entity);

        $this->assertContains('rental_rate', $alteration);
        $this->assertContains('rating', $alteration);
        $this->assertContains('special_features', $alteration);
        // Untouched columns of various types stay out of the diff.
        $this->assertNotContains('film_id', $alteration);
        $this->assertNotContains('title', $alteration);
        $this->assertNotContains('release_year', $alteration);
        $this->assertNotContains('last_update', $alteration);
    }
}
