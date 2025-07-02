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
use Hector\Orm\Query\Builder;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\RegularRelationship;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use stdClass;
use TypeError;

class AbstractRegularRelationshipTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );

        $this->assertInstanceOf(RegularRelationship::class, $relationship);
        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['language_id'], $relationship->getSourceColumns());
        $this->assertEquals(['language_id'], $relationship->getTargetColumns());
    }

    public function testConstructWithBadSourceEntity()
    {
        $this->expectException(TypeError::class);

        new ManyToOne(
            'language',
            stdClass::class,
            Language::class,
            ['language_id' => 'language_id']
        );
    }


    public function testConstructWithBadTargetEntity()
    {
        $this->expectException(TypeError::class);

        new ManyToOne(
            'language',
            Film::class,
            stdClass::class,
            ['language_id' => 'language_id']
        );
    }

    public function testGetName()
    {
        $relationship = new ManyToOne(
            $name = 'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );

        $this->assertEquals($name, $relationship->getName());
    }

    public function testGetSourceEntity()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );

        $this->assertEquals(Film::class, $relationship->getSourceEntity());
    }

    public function testGetSourceColumns()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language' => 'language_id']
        );

        $this->assertEquals(['language'], $relationship->getSourceColumns());
    }

    public function testGetSourceColumnsWithMultipleColumns()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['film' => 'film_id', 'language' => 'language_id']
        );

        $this->assertEquals(['film', 'language'], $relationship->getSourceColumns());
    }

    public function testGetTargetEntity()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );

        $this->assertEquals(Language::class, $relationship->getTargetEntity());
    }

    public function testGetTargetColumns()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language' => 'language_id']
        );

        $this->assertEquals(['language_id'], $relationship->getTargetColumns());
    }

    public function testGetTargetColumnsWithMultipleColumns()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language' => 'language_id', 'project' => 'project_id']
        );

        $this->assertEquals(['language_id', 'project_id'], $relationship->getTargetColumns());
    }

    public function testGetBuilder()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $builder = $relationship->getBuilder(Film::get(1));
        $binds = new BindParamList();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertEquals('(language_id) IN ( (:_h_0) )', $builder->where->getStatement($binds));
        $this->assertEquals(
            ['_h_0' => 1],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetBuilderWithBadEntity()
    {
        $this->expectException(TypeError::class);

        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $relationship->getBuilder(Language::get(1));
    }

    public function testGetBuilderWithoutEntities()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $binds = new BindParamList();

        $this->assertInstanceOf(Builder::class, $relationship->getBuilder());
        $this->assertNull($relationship->getBuilder()->where->getStatement($binds));
    }

    public function testGetBuilderWithClauses()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language' => 'language_id']
        );
        $relationship
            ->where('foo', '=', 'bar')
            ->orderBy('language.create_time', 'DESC')
            ->limit(10);

        $builder = $relationship->getBuilder();

        $this->assertNotSame($relationship->where, $builder->where);
        $this->assertNotSame($relationship->order, $builder->order);
        $this->assertNotSame($relationship->limit, $builder->limit);

        $this->assertNotEmpty($relationship->where);
        $this->assertNotEmpty($relationship->order);
        $this->assertNotEmpty($relationship->limit);

        $this->assertEquals($relationship->where, $builder->where);
        $this->assertEquals($relationship->order, $builder->order);
        $this->assertEquals($relationship->limit, $builder->limit);
    }

    public function testGet()
    {
        $relationship = new ManyToOne(
            'language',
            Film::class,
            Language::class,
            ['language_id' => 'language_id']
        );
        $article = Film::get(1);

        $this->assertFalse($article->getRelated()->isset('language'));

        $foreigners = $relationship->get($article);

        $this->assertTrue($article->getRelated()->isset('language'));
        $this->assertSame($article->getRelated()->language, $foreigners[0]);
    }
}
