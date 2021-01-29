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

use Hector\Orm\Exception\RelationException;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use ReflectionObject;

class RelatedTest extends AbstractTestCase
{
    public function testCount()
    {
        $related = (Film::get(1))->getRelated();

        $this->assertCount(0, $related);

        $related->language;

        $this->assertCount(1, $related);
    }

    public function testGetBuilder()
    {
        $related = (Film::get(1))->getRelated();
        $builder = $related->getBuilder('language');

        $reflectionObject = new ReflectionObject($builder);
        $entityReflectionProperty = $reflectionObject->getProperty('entityReflection');
        $entityReflectionProperty->setAccessible(true);

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertEquals(Language::class, $entityReflectionProperty->getValue($builder)->class);
    }

    public function testGetBuilderNonexistentRelationship()
    {
        $this->expectException(RelationException::class);

        $related = (new Film())->getRelated();
        $related->getBuilder('foo');
    }

    public function testGet()
    {
        $nbQueries = count($this->getOrm()->getConnection()->getLogger());
        $related = Film::get(1)->getRelated();
        $relatedData = $related->get('language');

        $this->assertInstanceOf(Language::class, $relatedData);
        $this->assertEquals(1, $relatedData->language_id);
        $this->assertCount($nbQueries + 2, $this->getOrm()->getConnection()->getLogger());
    }

    public function testGetWithManyToManyRelationship()
    {
        $nbQueries = count($this->getOrm()->getConnection()->getLogger());
        $related = Film::find(1)->getRelated();
        $relatedData = $related->get('actors');

        $this->assertInstanceOf(\Hector\Orm\Collection\Collection::class, $relatedData);
        $this->assertEquals(Actor::class, $relatedData->getAcceptedEntity());
        $this->assertGreaterThanOrEqual(2, count($relatedData));
        $this->assertCount($nbQueries + 2, $this->getOrm()->getConnection()->getLogger());
    }

    public function testGetEmptyEntity()
    {
        $nbQueries = count($this->getOrm()->getConnection()->getLogger());
        $related = (new Film())->getRelated();
        $relatedData = $related->get('language');

        $this->assertNull($relatedData);
        $this->assertCount($nbQueries, $this->getOrm()->getConnection()->getLogger());
    }

    public function testGetNonexistentRelationship()
    {
        $this->expectException(RelationException::class);

        $related = (new Film())->getRelated();
        $related->get('foo');
    }

    public function test__get()
    {
        $related = Film::get(1)->getRelated();
        $relatedData = $related->language;

        $this->assertInstanceOf(Language::class, $relatedData);
        $this->assertEquals(1, $relatedData->language_id);
    }

    public function testSet()
    {
        $related = Film::get(1)->getRelated();
        $related->set('language', $language = new Language());
        $relatedData = $related->get('language');

        $this->assertInstanceOf(Language::class, $relatedData);
        $this->assertSame($language, $relatedData);
    }

    public function testSetInvalidEntity()
    {
        $this->expectException(\InvalidArgumentException::class);

        $related = Film::get(1)->getRelated();
        $related->set('language', new Actor());
    }

    public function test__set()
    {
        $related = Film::get(1)->getRelated();
        $related->language = $language = new Language();
        $relatedData = $related->language;

        $this->assertInstanceOf(Language::class, $relatedData);
        $this->assertSame($language, $relatedData);
    }

    public function testIsset()
    {
        $related = Film::get(1)->getRelated();

        $this->assertFalse($related->isset('language'));

        $related->language;

        $this->assertTrue($related->isset('language'));
    }

    public function testIssetNonexistentRelationship()
    {
        $related = Film::get(1)->getRelated();

        $this->assertFalse($related->isset('foo'));
    }

    public function test__isset()
    {
        $related = Film::get(1)->getRelated();

        $this->assertFalse(isset($related->language));

        $related->language;

        $this->assertTrue(isset($related->language));
    }

    public function testUnset()
    {
        $related = Film::get(1)->getRelated();
        $related->language;

        $this->assertTrue(isset($related->language));

        $related->unset('language');

        $this->assertFalse(isset($related->language));
    }

    public function testUnsetNotGottenRelationship()
    {
        $related = Film::get(1)->getRelated();

        $this->assertFalse(isset($related->language));

        unset($related->foo);
        unset($related->language);

        $this->assertFalse(isset($related->language));
    }

    public function test__unset()
    {
        $related = Film::get(1)->getRelated();
        $related->language;

        $this->assertTrue(isset($related->language));

        unset($related->language);

        $this->assertFalse(isset($related->language));
    }

    public function testExists()
    {
        $related = Film::get(1)->getRelated();

        $this->assertFalse(isset($related->language));
        $this->assertTrue($related->exists('language'));
        $related->language;

        $this->assertTrue(isset($related->language));
        $this->assertTrue($related->exists('language'));

        $this->assertFalse($related->exists('foo'));
    }
}
