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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\ManyToMany;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use TypeError;

class ManyToManyTest extends AbstractTestCase
{
    public function testConstruct(): void
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

    public function testConstructWithDeductionOfPivotTable(): void
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

    public function testConstructWithDeductionOfColumns(): void
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('film_actor', $relationship->getPivotTable());
        $this->assertEquals(['film_id'], $relationship->getSourceColumns());
        $this->assertEquals(['film_id'], $relationship->getPivotTargetColumns());
        $this->assertEquals(['actor_id'], $relationship->getPivotSourceColumns());
        $this->assertEquals(['actor_id'], $relationship->getTargetColumns());
    }

    public function testGetBuilder(): void
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $builder = $relationship->getBuilder(Film::find(1), Film::find(2));
        $binds = new BindParamList();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertEquals(
            '( film_id ) IN ( (:_h_0), (:_h_1) )',
            $builder->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 1,
                '_h_1' => 2,
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetBuilderWithBadEntity(): void
    {
        $this->expectException(TypeError::class);

        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $relationship->getBuilder(Language::get(2));
    }

    public function testGet(): void
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $film = Film::get(1);
        $this->assertFalse($film->getRelated()->isset('actors'));
        $foreigners = $relationship->get($film);
        $this->assertTrue($film->getRelated()->isset('actors'));
        $this->assertInstanceOf(Collection::class, $film->getRelated()->actors);
        $this->assertEquals($film->getRelated()->actors, $foreigners);
    }

    public function testGetPivotTargetColumns(): void
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

    public function testGetPivotSourceColumns(): void
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

    public function testReverse(): void
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

    public function testLinkNative(): void
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

    public function testLinkNativeDoesNotDuplicateLoadedCollection(): void
    {
        $relationship = new ManyToMany('actors', Film::class, Actor::class);
        $film = Film::get(2);

        // Force the relation to be loaded so getRelated()->get('actors') returns the
        // same instance that we pass as $foreign (the aliasing that caused doubling).
        $loaded = $film->getActors();
        $initialCount = count($loaded);
        $this->assertGreaterThan(0, $initialCount);

        $relationship->linkNative($film, $loaded);

        // The loaded collection must not have doubled.
        $this->assertCount($initialCount, $film->getActors());

        // Idempotent: a second pass must not grow it either.
        $relationship->linkNative($film, $film->getActors());
        $this->assertCount($initialCount, $film->getActors());
    }

    public function testLinkNativeReattachLoadedEntityKeepsPivotKeys(): void
    {
        // An actor loaded through a M2M relation carries a PivotData whose extra data
        // is empty. Re-attaching it to another film must not raise and must insert the
        // pivot row with the correct foreign keys.
        $relationship = new ManyToMany('actors', Film::class, Actor::class);

        $connection = $this->getOrm()->getConnection();

        $film1 = Film::get(1);
        $film2 = Film::get(2);

        // Pick an actor loaded through film1's M2M relation (so it carries a PivotData
        // whose extra data is empty) that is genuinely NOT yet linked to film2 in the
        // database, to exercise the INSERT branch.
        /** @var Actor|null $loadedActor */
        $loadedActor = $film1->getActors()->first(
            function (Actor $actor) use ($connection, $film2): bool {
                $count = $connection->fetchColumn(
                    'SELECT COUNT(*) FROM film_actor WHERE film_id = ? AND actor_id = ?',
                    [$film2->film_id, $actor->actor_id]
                );

                return 0 === (int)($count[0] ?? 0);
            }
        );
        $this->assertNotNull($loadedActor);
        $this->assertNotNull($loadedActor->getPivot());
        $this->assertSame([], $loadedActor->getPivot()->getData());

        // Must not throw RelationException nor insert a FK-less pivot row.
        $relationship->linkNative($film2, new Collection([$loadedActor]));

        $count = $connection->fetchColumn(
            'SELECT COUNT(*) FROM film_actor WHERE film_id = ? AND actor_id = ?',
            [$film2->film_id, $loadedActor->actor_id]
        );

        $this->assertSame(1, (int)($count[0] ?? 0));
    }
}
