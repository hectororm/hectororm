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
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;

class RelationshipAttributeTest extends AbstractTestCase
{
    public function testInitWithClauses()
    {
        $attribute = new HasMany(
            Film::class,
            'films',
            where: ['language' => 1],
            groupBy: ['actor_id'],
            having: ['rating' => 'G'],
            orderBy: ['last_update' => 'DESC', 'title'],
            limit: [10, 5],
        );

        $relationships = new Relationships(Language::class);
        $relationship = $attribute->init($relationships);

        $this->assertCount(1, $relationship->where);
        $this->assertCount(1, $relationship->having);
        $this->assertCount(1, $relationship->group);
        $this->assertCount(2, $relationship->order);
        $this->assertEquals(10, $relationship->limit->getLimit());
        $this->assertEquals(5, $relationship->limit->getOffset());
    }
}
