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
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\ManyToMany;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;

class ManyToManyTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $relationship = new ManyToMany(
            'actors',
            Film::class,
            Actor::class,
            'film_actor',
            ['film_id' => 'id_film'],
            ['id_actor' => 'actor_id']
        );

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('film_actor', $relationship->getPivotTable());
        $this->assertEquals(['film_id'], $relationship->getSourceColumns());
        $this->assertEquals(['id_film'], $relationship->getPivotTargetColumns());
        $this->assertEquals(['id_actor'], $relationship->getPivotSourceColumns());
        $this->assertEquals(['actor_id'], $relationship->getTargetColumns());
    }

    public function testConstructWithDeductionOfPivotTable()
    {
        $relationship = new ManyToMany(
            'actors',
            Film::class,
            Actor::class,
            null,
            ['film_id' => 'id_film'],
            ['id_actor' => 'actor_id']
        );

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('film_actor', $relationship->getPivotTable());
        $this->assertEquals(['film_id'], $relationship->getSourceColumns());
        $this->assertEquals(['id_film'], $relationship->getPivotTargetColumns());
        $this->assertEquals(['id_actor'], $relationship->getPivotSourceColumns());
        $this->assertEquals(['actor_id'], $relationship->getTargetColumns());
    }

    public function testConstructWithDeductionOfColumns()
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('film_actor', $relationship->getPivotTable());
        $this->assertEquals(['film_id'], $relationship->getSourceColumns());
        $this->assertEquals(['film_id'], $relationship->getPivotTargetColumns());
        $this->assertEquals(['actor_id'], $relationship->getPivotSourceColumns());
        $this->assertEquals(['actor_id'], $relationship->getTargetColumns());
    }

    public function testGetBuilder()
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $builder = $relationship->getBuilder(Film::find(1), Film::find(2));
        $binding = [];

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertEquals(
            '    (film_id) IN ((?), (?))' . PHP_EOL,
            $builder->where->getStatement($binding)
        );
        $this->assertEquals(
            [
                1,
                2,
            ],
            $binding
        );
    }

    public function testGetBuilderWithBadEntity()
    {
        $this->expectException(RelationException::class);

        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $relationship->getBuilder(Language::get(2));
    }

    public function testGet()
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $film = Film::get(1);
        $this->assertFalse($film->getRelated()->isset('actors'));
        $foreigners = $relationship->get($film);
        $this->assertTrue($film->getRelated()->isset('actors'));
        $this->assertInstanceOf(Collection::class, $film->getRelated()->actors);
        $this->assertEquals($film->getRelated()->actors, $foreigners);
    }

    public function testGetPivotTargetColumns()
    {
        $relationship = new ManyToMany(
            'actors',
            Film::class,
            Actor::class,
            'film_actor',
            ['film_id' => 'id_film', 'actor_id' => 'actor'],
            ['id_actor' => 'actor_id']
        );

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['id_film', 'actor'], $relationship->getPivotTargetColumns());
    }

    public function testGetPivotSourceColumns()
    {
        $relationship = new ManyToMany(
            'actors',
            Film::class,
            Actor::class,
            'film_actor',
            ['film_id' => 'id_film', 'actor_id' => 'actor'],
            ['id_actor' => 'actor_id']
        );

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['id_actor'], $relationship->getPivotSourceColumns());
    }

    public function testReverse()
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $reverse = $relationship->reverse('films');

        $this->assertInstanceOf(ManyToMany::class, $reverse);
        $this->assertEquals('films', $reverse->getName());
        $this->assertEquals($reverse->getSourceEntity(), $relationship->getTargetEntity());
        $this->assertEquals($reverse->getSourceColumns(), $relationship->getTargetColumns());
        $this->assertEquals($reverse->getPivotTable(), $relationship->getPivotTable());
        $this->assertEquals($reverse->getPivotSourceColumns(), $relationship->getPivotTargetColumns());
        $this->assertEquals($reverse->getPivotTargetColumns(), $relationship->getPivotSourceColumns());
        $this->assertEquals($reverse->getTargetEntity(), $relationship->getSourceEntity());
        $this->assertEquals($reverse->getTargetColumns(), $relationship->getSourceColumns());
    }

    public function testLinkNative()
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $film = Film::get(2);
        $actor = new Actor();
        $actor->first_name = 'Foo';
        $actor->last_name = 'Bar';

        $initialCount = count($film->getActors());

        $relationship->linkNative($film, new Collection([$actor]));

        $this->assertNotNull($actor->actor_id);
        $this->assertCount(($initialCount + 1), $film->getActors());
        $this->assertTrue($film->getActors()->contains($actor));
    }
}
