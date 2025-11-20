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

use Hector\Orm\Exception\OrmException;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\FilmMagic;
use Hector\Orm\Tests\Fake\Entity\Language;

class MagicEntityTest extends AbstractTestCase
{
    public function testGetOnExistentEntity(): void
    {
        $entity = FilmMagic::get(1);

        $this->assertNotEmpty($entity->title);
    }

    public function testGetOnNewEntity(): void
    {
        $entity = new FilmMagic();

        $this->assertNull($entity->title);
    }

    public function testGetOnExistentEntityWithUnknownProperty(): void
    {
        $this->expectException(OrmException::class);

        $entity = FilmMagic::get(1);
        $entity->foo;
    }

    public function testGetOnNewEntityWithUnknownProperty(): void
    {
        $this->expectException(OrmException::class);

        $entity = new FilmMagic();
        $entity->foo;
    }

    public function testSetOnExistentEntity(): void
    {
        $entity = FilmMagic::get(1);
        $entity->title = 'Hector Film';

        $this->assertEquals('Hector Film', $entity->title);
    }

    public function testSetOnNewEntity(): void
    {
        $entity = new FilmMagic();
        $entity->title = 'Hector Film';

        $this->assertEquals('Hector Film', $entity->title);
    }

    public function testSetOnExistentEntityWithUnknownProperty(): void
    {
        $this->expectException(OrmException::class);

        $entity = FilmMagic::get(1);
        $entity->foo = 'Bar';
    }

    public function testSetOnNewEntityWithUnknownProperty(): void
    {
        $this->expectException(OrmException::class);

        $entity = new FilmMagic();
        $entity->foo = 'Bar';
    }

    public function testIssetOnExistentEntity(): void
    {
        $entity = FilmMagic::get(1);

        $this->assertTrue(isset($entity->film_id));
        $this->assertFalse(isset($entity->foo));
    }

    public function testIssetOnNewEntity(): void
    {
        $entity = new FilmMagic();

        $this->assertTrue(isset($entity->film_id));
        $this->assertFalse(isset($entity->foo));
    }

    public function testRelation(): void
    {
        /** @var FilmMagic $entity */
        $entity = FilmMagic::query()->get();

        $this->assertInstanceOf(FilmMagic::class, $entity);

        $related = $entity->language;

        $this->assertInstanceOf(Language::class, $related);
        $this->assertEquals($entity->language_id, $related->language_id);
    }

    public function testRelationSame(): void
    {
        /** @var FilmMagic $entity */
        $entity = FilmMagic::query()->get();

        $this->assertInstanceOf(FilmMagic::class, $entity);
        $this->assertSame($entity->language, $entity->language);
    }
}
