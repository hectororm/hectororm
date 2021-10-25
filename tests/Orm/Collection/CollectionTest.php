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

namespace Hector\Orm\Tests\Collection;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Staff;
use InvalidArgumentException;
use stdClass;
use TypeError;

class CollectionTest extends AbstractTestCase
{
    public function testCollectionWithBadRestriction()
    {
        $this->expectException(TypeError::class);

        new Collection([], stdClass::class);
    }

    public function testCollectionConstructWithEntities()
    {
        $films = new Collection([$film = new Film()]);

        $this->assertCount(1, $films);
    }

    public function testCollectionConstructWithBadEntities()
    {
        $this->expectException(TypeError::class);

        new Collection([new Film()], Actor::class);
    }

    public function testUpdateHook()
    {
        $film1 = new Film();
        $film1->title = 'Foo';
        $film2 = new Film();
        $film2->title = 'Bar';
        $film3 = new Film();
        $film3->title = 'Baz';

        $films = new CollectionWithHook([$film1]);

        $this->assertEquals('Foo', $films->getHookValue());

        $films->exchangeArray([$film2, $film3]);

        $this->assertEquals('Bar, Baz', $films->getHookValue());

        unset($films[0]);

        $this->assertEquals('Baz', $films->getHookValue());

        $films[] = $film1;

        $this->assertEquals('Baz, Foo', $films->getHookValue());
    }

    public function testExchangeArray()
    {
        $films = new Collection();
        $films->exchangeArray([$film = new Film()]);

        $this->assertCount(1, $films);
    }

    public function testExchangeArrayWithBadEntities()
    {
        $this->expectException(TypeError::class);

        $films = new Collection([], Actor::class);
        $films->exchangeArray([new Film()]);
    }

    public function testFilter()
    {
        $collection = new Collection();
        $collection->append(new Film());
        $collection->append(new Film());
        $collection->append(new Actor());
        $collection->append(new Actor());
        $collection->append(new Actor());

        $this->assertCount(3, $collection->filter(fn($entity) => is_a($entity, Actor::class, true)));
    }

    public function testCollectionAppendEntity()
    {
        $films = new Collection();

        $this->assertCount(0, $films);

        $films->append($film = new Film());

        $this->assertCount(1, $films);
        $this->assertTrue(isset($films[0]));
        $this->assertSame($film, $films[0]);
    }

    public function testCollectionAppendInvalidEntity()
    {
        $this->expectException(TypeError::class);

        $films = new Collection([], Actor::class);
        $films->append(new Film());
    }

    public function testCollectionAppend2InvalidEntity()
    {
        $this->expectException(TypeError::class);

        $films = new Collection([], Actor::class);
        $films[] = new Film();
    }

    public function testCollectionAppend3InvalidEntity()
    {
        $this->expectException(TypeError::class);

        $films = new Collection([], Actor::class);
        $films['foo'] = new Film();
    }

    public function testSave()
    {
        $films = [
            Film::get(1),
            Film::get(2),
            $newFilm = new Film()
        ];
        $newFilm->language_id = 1;
        $newFilm->title = 'Hector Film';
        $newFilm->description = 'Hector Film description';

        $collection = new Collection($films);
        $collection->save();

        foreach ($films as $film) {
            $this->assertNotNull($film->film_id);
        }
    }

    public function testDelete()
    {
        $films = [
            new Film(),
            $newFilm = new Film()
        ];
        $newFilm->language_id = 1;
        $newFilm->title = 'Hector Film';
        $newFilm->description = 'Hector Film description';
        $newFilm->save();

        $collection = new Collection($films);

        $this->assertNotNull($newId = $newFilm->film_id);
        $this->assertCount(2, $collection);

        $collection->delete();

        $this->assertCount(0, $collection);
        $this->assertNull(Film::get($newId));
    }

    public function testRefresh()
    {
        $films = [
            $film1 = Film::get(1),
            $newFilm = new Film()
        ];
        $oldTitleFilm1 = $film1->title;
        $film1->title = 'ACADEMY DINOSAUR modified';

        $newFilm->language_id = 1;
        $newFilm->title = $newFilmTitle = 'Hector Film';
        $newFilm->description = 'Hector Film description';

        $collection = new Collection($films);
        $collection->refresh();

        $this->assertEquals($oldTitleFilm1, $film1->title);
        $this->assertEquals($newFilmTitle, $newFilm->title);
        $this->assertNull($newFilm->film_id);
    }

    public function testSaveEmptyCollection()
    {
        $this->expectNotToPerformAssertions();

        $collection = new Collection();
        $collection->save();
    }

    public function testDeleteEmptyCollection()
    {
        $this->expectNotToPerformAssertions();

        $collection = new Collection();
        $collection->delete();
    }

    public function testRefreshEmptyCollection()
    {
        $this->expectNotToPerformAssertions();

        $collection = new Collection();
        $collection->refresh();
    }

    public function testContains()
    {
        $collection = Film::query()->limit(100)->all();
        $entity = $collection[0];

        $this->assertTrue($collection->contains($entity));
    }

    public function testIsInFalse()
    {
        $collection = Film::query()->limit(100)->all();
        $entity = new Film();

        $this->assertFalse($collection->contains($entity));
    }

    public function testIsInFalseWithNotAcceptedEntity()
    {
        $collection = Film::query()->limit(100)->all();
        $entity = new Staff();

        $this->assertFalse($collection->contains($entity));
    }

    public function testLoad()
    {
        $logger = $this->getOrm()->getConnection()->getLogger();
        $nbLogEntries = count($logger);

        $staffs = Staff::all(1, 2);
        $this->assertEquals(1, count($logger) - $nbLogEntries);

        $staffs->load(['address' => ['city' => ['country']]]);

        $this->assertCount(2, $staffs);
        $this->assertEquals(3, $staffs[0]->address->address_id);
        $this->assertEquals(4, $staffs[1]->address->address_id);
        $this->assertEquals(300, $staffs[0]->address->getCity()->city_id);
        $this->assertEquals(576, $staffs[1]->address->getCity()->city_id);
        $this->assertEquals(4, count($logger) - $nbLogEntries);
    }
}
