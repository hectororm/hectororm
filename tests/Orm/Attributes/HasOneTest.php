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

use Hector\Orm\Attributes\HasOne;
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\Fake\Entity\Address;
use Hector\Orm\Tests\Fake\Entity\Customer;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

class HasOneTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new HasOne(Address::class, 'address', ['address_id' => 'address_id']);

        $this->assertEquals(Address::class, $attribute->target);
        $this->assertEquals('address', $attribute->name);
        $this->assertEquals(['address_id' => 'address_id'], $attribute->columns);
    }

    public function testConstructWithoutColumns()
    {
        $attribute = new HasOne(Address::class, 'address');

        $this->assertEquals(Address::class, $attribute->target);
        $this->assertEquals('address', $attribute->name);
        $this->assertNull($attribute->columns);
    }

    public function testConstructBadEntity()
    {
        $this->expectException(TypeError::class);

        new HasOne(stdClass::class, 'address');
    }

    public function testInit()
    {
        $attribute = new HasOne(Address::class, 'address', ['address_id' => 'address_id']);
        $relationships = new Relationships(Customer::class);
        $attribute->init($relationships);

        $this->assertInstanceOf(ManyToOne::class, $relationships->get('address'));
        $this->assertEquals('address', $relationships->get('address')->getName());
        $this->assertEquals(Address::class, $relationships->get('address')->getTargetEntity());
    }
}
