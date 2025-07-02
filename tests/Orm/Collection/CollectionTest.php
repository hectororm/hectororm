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

    public function testContainsFalse()
    {
        $collection = Film::query()->limit(100)->all();
        $entity = new Film();

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
