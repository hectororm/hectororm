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

namespace Hector\Orm\Tests\Attributes;

use Hector\Orm\Attributes\BelongsToMany;
use Hector\Orm\Relationship\ManyToMany;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
use stdClass;
use TypeError;

class BelongsToManyTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $attribute = new BelongsToMany(
            Actor::class,
            'actors',
            'film_actor',
            ['film_id' => 'film_id'],
            ['actor_id' => 'actor_id']
        );

        $this->assertEquals(Actor::class, $attribute->target);
        $this->assertEquals('actors', $attribute->name);
        $this->assertEquals('film_actor', $attribute->pivotTable);
        $this->assertEquals(['film_id' => 'film_id'], $attribute->columnsFrom);
        $this->assertEquals(['actor_id' => 'actor_id'], $attribute->columnsTo);
    }

    public function testConstructWithoutColumns()
    {
        $attribute = new BelongsToMany(Actor::class, 'actors');

        $this->assertEquals(Actor::class, $attribute->target);
        $this->assertEquals('actors', $attribute->name);
        $this->assertNull($attribute->pivotTable);
        $this->assertNull($attribute->columnsFrom);
        $this->assertNull($attribute->columnsTo);
    }

    public function testConstructBadEntity()
    {
        $this->expectException(TypeError::class);

        new BelongsToMany(stdClass::class, 'acors');
    }

    public function testInit()
    {
        $attribute = new BelongsToMany(
            Actor::class,
            'actors',
            'film_actor',
            ['film_id' => 'film_id'],
            ['actor_id' => 'actor_id']
        );

        $relationships = new Relationships(Film::class);
        /** @var ManyToMany $relationship */
        $relationship = $attribute->init($relationships);

        $this->assertInstanceOf(ManyToMany::class, $relationship);
        $this->assertEquals('actors', $relationship->getName());
        $this->assertEquals(Actor::class, $relationship->getTargetEntity());
        $this->assertEquals('film_actor', $relationship->getPivotTable());
        $this->assertEquals(['film_id'], $relationship->getSourceColumns());
        $this->assertEquals(['film_id'], $relationship->getPivotTargetColumns());
        $this->assertEquals(['actor_id'], $relationship->getPivotSourceColumns());
        $this->assertEquals(['actor_id'], $relationship->getTargetColumns());
    }
}
