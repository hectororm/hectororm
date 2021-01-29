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

use Hector\Orm\Exception\MapperException;
use Hector\Orm\Mapper\GenericMapper;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\FilmIncomplete;
use Hector\Orm\Tests\Fake\Entity\Language;

class GenericMapperTest extends AbstractTestCase
{
    public function testHydrateEntity()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = new Film();
        $mapper->hydrateEntity($entity, ['film_id' => 1, 'description' => 'foo', 'unknown' => 'bar']);

        $this->assertEquals(1, $entity->film_id);
        $this->assertEquals('foo', $entity->description);
    }

    public function testHydrateEntityWithIncompleteEntity()
    {
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = new FilmIncomplete();
        $mapper->hydrateEntity($entity, ['film_id' => 1, 'description' => 'foo', 'unknown' => 'bar']);

        $this->assertEquals(1, $entity->film_id);
    }

    public function testHydrateEntityWithBadEntity()
    {
        $this->expectException(MapperException::class);

        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = new Language();
        $mapper->hydrateEntity($entity, ['author_id' => 1]);
    }

    public function testCollectEntity()
    {
        $entity = new Film();
        $entity->film_id = 123;
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertEquals(
            [
                'film_id' => 123,
                'title' => 'Foo',
                'description' => 'Bar',
                'release_year' => null,
                'language_id' => null,
                'original_language_id' => null,
                'rental_duration' => null,
                'rental_rate' => null,
                'length' => null,
                'replacement_cost' => null,
                'rating' => null,
                'special_features' => null,
                'last_update' => null,
            ],
            $mapper->collectEntity($entity)
        );
    }

    public function testCollectEntityWithSpecifiedColumns()
    {
        $entity = new Film();
        $entity->film_id = 123;
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());

        $this->assertEquals(
            [
                'film_id' => 123,
                'title' => 'Foo',
            ],
            $mapper->collectEntity($entity, ['film_id', 'title'])
        );
    }

    public function testCollectEntityWithBadEntity()
    {
        $this->expectException(MapperException::class);

        $mapper = new GenericMapper(Film::class, $this->getOrm()->getStorage());
        $entity = new Language();
        $mapper->collectEntity($entity);
    }
}
