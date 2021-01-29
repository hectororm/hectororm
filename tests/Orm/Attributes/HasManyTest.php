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

use Hector\Orm\Attributes\HasMany;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

class HasManyTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new HasMany(Film::class, 'films', ['language_id' => 'language_id']);

        $this->assertEquals(Film::class, $attribute->target);
        $this->assertEquals('films', $attribute->name);
        $this->assertEquals(['language_id' => 'language_id'], $attribute->columns);
    }

    public function testConstructWithoutColumns()
    {
        $attribute = new HasMany(Film::class, 'films');

        $this->assertEquals(Film::class, $attribute->target);
        $this->assertEquals('films', $attribute->name);
        $this->assertNull($attribute->columns);
    }

    public function testConstructBadEntity()
    {
        $this->expectException(TypeError::class);

        new HasMany(stdClass::class, 'films');
    }

    public function testInit()
    {
        $attribute = new HasMany(Film::class, 'films', ['language_id' => 'language_id']);
        $relationships = new Relationships(Language::class);
        $attribute->init($relationships);

        $this->assertInstanceOf(OneToMany::class, $relationships->get('films'));
        $this->assertEquals('films', $relationships->get('films')->getName());
        $this->assertEquals(Film::class, $relationships->get('films')->getTargetEntity());
    }
}
