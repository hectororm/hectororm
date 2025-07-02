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

namespace Hector\Orm\Tests\Entity;

use Hector\Orm\Attributes;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Mapper\GenericMapper;
use Hector\Orm\Mapper\MagicMapper;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\City;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\FilmMagic;
use Hector\Orm\Tests\Fake\Entity\Language;
use Hector\Orm\Tests\Fake\Entity\LanguageCollection;
use Hector\Orm\Tests\Fake\Mapper\CityMapper;
use stdClass;
use TypeError;

class ReflectionEntityTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $reflectionEntity = new ReflectionEntity(Film::class);

        $this->assertEquals(Film::class, $reflectionEntity->class);
    }

    public function testConstruct_badEntity()
    {
        $this->expectException(TypeError::class);

        new ReflectionEntity(stdClass::class);
    }

    public function testConstruct_badMapper()
    {
        $this->expectException(TypeError::class);

        $entity = new #[Attributes\Mapper(stdClass::class)] class extends Film {
        };

        new ReflectionEntity(get_class($entity));
    }

    public function testSerialization()
    {
        $reflectionEntity = new ReflectionEntity(Film::class);
        $serialized = serialize($reflectionEntity);
        $unserialized = unserialize($serialized);

        $this->assertEquals($reflectionEntity->__serialize(), $unserialized->__serialize());
    }

    public function testMapperProperty()
    {
        $reflectionEntity = new ReflectionEntity(Film::class);
        $this->assertEquals(GenericMapper::class, $reflectionEntity->mapper);

        $reflectionEntity = new ReflectionEntity(FilmMagic::class);
        $this->assertEquals(MagicMapper::class, $reflectionEntity->mapper);

        $reflectionEntity = new ReflectionEntity(City::class);
        $this->assertEquals(CityMapper::class, $reflectionEntity->mapper);
    }

    public function testNewInstance()
    {
        $reflectionEntity = new ReflectionEntity(Film::class);
        $entity = $reflectionEntity->newInstance();

        $this->assertInstanceOf(Film::class, $entity);
    }

    public function testNewInstanceOfMapper()
    {
        $reflectionEntity = new ReflectionEntity(Film::class);
        $mapper = $reflectionEntity->newInstanceOfMapper($this->getOrm()->getStorage());
        $this->assertInstanceOf(GenericMapper::class, $mapper);

        $reflectionEntity = new ReflectionEntity(City::class);
        $mapper = $reflectionEntity->newInstanceOfMapper($this->getOrm()->getStorage());
        $this->assertInstanceOf(CityMapper::class, $mapper);
    }
}
