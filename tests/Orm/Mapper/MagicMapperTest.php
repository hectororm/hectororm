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
use Hector\Orm\Mapper\MagicMapper;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\FilmMagic;
use Hector\Orm\Tests\Fake\Entity\Language;

class MagicMapperTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());

        $this->assertInstanceOf(MagicMapper::class, $mapper);
    }

    public function testConstructWithBadEntity()
    {
        $this->expectException(MapperException::class);

        new MagicMapper(Film::class, $this->getOrm()->getStorage());
    }

    public function testHydrateEntity()
    {
        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());
        $entity = new FilmMagic();
        $mapper->hydrateEntity($entity, ['film_id' => 1, 'description' => 'foo', 'unknown' => 'bar']);

        $this->assertEquals(1, $entity->film_id);
        $this->assertEquals('foo', $entity->description);
    }

    public function testHydrateEntityWithBadEntity()
    {
        $this->expectException(MapperException::class);

        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());
        $entity = new Language();
        $mapper->hydrateEntity($entity, ['language_id' => 1]);
    }

    public function testCollectEntity()
    {
        $entity = new FilmMagic();
        $entity->film_id = 123;
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());

        $this->assertEquals(
            [
                'film_id' => 123,
                'title' => 'Foo',
                'description' => 'Bar'
            ],
            $mapper->collectEntity($entity)
        );
    }

    public function testCollectEntityWithSpecifiedColumns()
    {
        $entity = new FilmMagic();
        $entity->film_id = 123;
        $entity->title = 'Foo';
        $entity->description = 'Bar';
        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());

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

        $mapper = new MagicMapper(FilmMagic::class, $this->getOrm()->getStorage());
        $entity = new Language();
        $mapper->collectEntity($entity);
    }
}
