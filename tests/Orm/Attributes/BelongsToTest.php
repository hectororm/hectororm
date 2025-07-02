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

use Hector\Orm\Attributes\BelongsTo;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Relationship\Relationships;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Address;
use Hector\Orm\Tests\Fake\Entity\Customer;
use stdClass;
use TypeError;

class BelongsToTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $attribute = new BelongsTo(Customer::class, 'customer', 'address');

        $this->assertEquals(Customer::class, $attribute->target);
        $this->assertEquals('customer', $attribute->name);
        $this->assertEquals('address', $attribute->foreignName);
    }

    public function testConstructWithoutForeignName()
    {
        $attribute = new BelongsTo(Customer::class, 'customer');

        $this->assertEquals(Customer::class, $attribute->target);
        $this->assertEquals('customer', $attribute->name);
        $this->assertNull($attribute->foreignName);
    }

    public function testConstructBadEntity()
    {
        $this->expectException(TypeError::class);

        new BelongsTo(stdClass::class, 'customer');
    }

    public function testInit()
    {
        $attribute = new BelongsTo(Customer::class, 'customer', 'address');
        $relationships = new Relationships(Address::class);
        $attribute->init($relationships);

        $this->assertInstanceOf(Relationship::class, $relationships->get('customer'));
        $this->assertEquals('customer', $relationships->get('customer')->getName());
        $this->assertEquals(Customer::class, $relationships->get('customer')->getTargetEntity());
    }
}
