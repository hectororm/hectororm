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

use Hector\Orm\Exception\RelationException;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use stdClass;
use TypeError;

class RelationshipsTest extends AbstractTestCase
{
    public function testConstruct(): void
    {
        $this->expectNotToPerformAssertions();
        new Relationships(Film::class);
    }

    public function testConstructBadEntity(): void
    {
        $this->expectException(TypeError::class);
        new Relationships(stdClass::class);
    }

    public function testGet(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->assertInstanceOf(Relationship::class, $relationships->get('foo'));
    }

    public function testGetWith(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);
        $relationships->hasMany(Actor::class, 'qux', []);
        $relationships->hasMany(Language::class, 'bar', []);

        $relationship = $relationships->getWith(Language::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('bar', $relationship->getName());
    }

    public function testGetWith_ambiguous(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);
        $relationships->hasMany(Actor::class, 'qux', []);
        $relationships->hasMany(Language::class, 'bar', []);

        $this->expectException(RelationException::class);
        $relationships->getWith(Actor::class);
    }

    public function testGetWith_nameSpecified(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);
        $relationships->hasMany(Actor::class, 'qux', []);
        $relationships->hasMany(Language::class, 'bar', []);

        $relationship = $relationships->getWith(Actor::class, 'qux');

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals('qux', $relationship->getName());
    }

    public function testGetWith_nameSpecifiedButBadEntity(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);
        $relationships->hasMany(Language::class, 'bar', []);

        $this->expectException(RelationException::class);
        $relationships->getWith(Language::class, 'foo');
    }

    public function testGetWith_notFound(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->expectException(RelationException::class);
        $relationships->getWith(Language::class);
    }

    public function testGetWith_notFoundWithNameSpecified(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->expectException(RelationException::class);
        $relationships->getWith(Language::class, 'qux');
    }

    public function testGetNonexistent(): void
    {
        $relationships = new Relationships(Film::class);

        $this->expectException(RelationException::class);
        $relationships->get('foo');
    }

    public function testExistsTrue(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->assertTrue($relationships->exists('foo'));
    }

    public function testExistsFalse(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->assertFalse($relationships->exists('bar'));
    }

    public function testAssertExistsTrue(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->expectNotToPerformAssertions();
        $relationships->assertExists('foo');
    }

    public function testAssertExistsFalse(): void
    {
        $relationships = new Relationships(Film::class);
        $relationships->hasMany(Actor::class, 'foo', []);

        $this->expectException(RelationException::class);
        $relationships->assertExists('bar');
    }

    public function testHasOne(): void
    {
        $relationships = new Relationships(Film::class);
        $relation = $relationships->hasOne(Actor::class, 'foo', []);

        $this->assertInstanceOf(ManyToOne::class, $relation);
    }

    public function testHasOneWithBadEntity(): void
    {
        $this->expectException(TypeError::class);

        $relationships = new Relationships(Film::class);
        $relationships->hasOne(stdClass::class, 'foo', []);
    }

    public function testHasMany(): void
    {
        $relationships = new Relationships(Film::class);
        $relation = $relationships->hasMany(Actor::class, 'foo', []);

        $this->assertInstanceOf(OneToMany::class, $relation);
    }

    public function testHasManyWithBadEntity(): void
    {
        $this->expectException(TypeError::class);

        $relationships = new Relationships(Film::class);
        $relationships->hasMany(stdClass::class, 'foo', []);
    }

    public function testBelongsTo(): void
    {
        $relationships = new Relationships(Language::class);
        $relation = $relationships->belongsTo(Film::class, 'films', 'language');

        $this->assertInstanceOf(OneToMany::class, $relation);
    }

    public function testBelongsToWithBadEntity(): void
    {
        $this->expectException(TypeError::class);

        $relationships = new Relationships(Language::class);
        $relationships->belongsTo(stdClass::class, 'films');
    }
}
