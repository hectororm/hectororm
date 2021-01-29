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

namespace Hector\Orm\Tests\Relationship;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;

class OneToManyTest extends AbstractTestCase
{
    public function testConstructWithDeductionOfColumns()
    {
        $relationship = new OneToMany('films', Language::class, Film::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['language_id'], $relationship->getSourceColumns());
        $this->assertEquals(['language_id'], $relationship->getTargetColumns());
    }

    public function testValid()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertTrue($relationship->valid(Film::query()->limit(20)->all()));
    }

    public function testValidWithNull()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertTrue($relationship->valid(null));
    }

    public function testValidWithEntity()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertFalse($relationship->valid(Film::get(1)));
    }

    public function testValidWithCollection()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertTrue($relationship->valid(new Collection([], Film::class)));
    }

    public function testValidWithCollectionWithBadEntities()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertFalse($relationship->valid(new Collection([], Actor::class)));
    }

    public function testValidWithCollectionWithNoRestrictionButGoodEntities()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertTrue($relationship->valid(new Collection([new Film(), new Film()])));
    }

    public function testValidWithCollectionWithNoRestrictionButBadEntities()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $this->assertFalse($relationship->valid(new Collection([new Actor(), new Film()])));
    }

    public function testSwitchIntoEntities()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $reflectionMethod = new \ReflectionMethod(OneToMany::class, 'switchIntoEntities');
        $reflectionMethod->setAccessible(true);

        $foreigners = new Collection(
            [$film1 = Film::find(1), $film2 = Film::find(2), Actor::find(2), Actor::find(3)]
        );
        $reflectionMethod->invoke(
            $relationship,
            $foreigners,
            $language1 = Language::find(1),
            $language2 = Language::find(2)
        );

        $this->assertTrue($language1->getRelated()->isset('films'));
        $this->assertTrue($language2->getRelated()->isset('films'));
        $this->assertInstanceOf(Collection::class, $language1->getRelated()->films);
        $this->assertInstanceOf(Collection::class, $language2->getRelated()->films);
        $this->assertCount(2, $language1->getRelated()->films);
        $this->assertCount(0, $language2->getRelated()->films);
        $this->assertTrue($language1->getRelated()->films->contains($film1));
        $this->assertTrue($language1->getRelated()->films->contains($film2));
        $this->assertFalse($language2->getRelated()->films->contains($film2));
        $this->assertFalse($language2->getRelated()->films->contains($film1));
    }

    public function testLinkNative()
    {
        $relationship = new OneToMany(
            'films',
            Language::class,
            Film::class,
            ['language_id' => 'language_id']
        );

        $language = Language::get(1);
        $film = new Film();
        $film->title = 'Foo';
        $film->content = 'Bar';

        $relationship->linkNative($language, new Collection([$film]));

        $this->assertNotNull($film->language_id);
        $this->assertEquals($film->language_id, $language->language_id);
    }

    public function testReverse()
    {
        $relationship = new OneToMany(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $reverse = $relationship->reverse('film');

        $this->assertInstanceOf(ManyToOne::class, $reverse);
        $this->assertEquals('film', $reverse->getName());
        $this->assertEquals($reverse->getSourceEntity(), $relationship->getTargetEntity());
        $this->assertEquals($reverse->getSourceColumns(), $relationship->getTargetColumns());
        $this->assertEquals($reverse->getTargetEntity(), $relationship->getSourceEntity());
        $this->assertEquals($reverse->getTargetColumns(), $relationship->getSourceColumns());
    }
}
