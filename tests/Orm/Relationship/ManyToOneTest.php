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
use Hector\Orm\Tests\Fake\Entity\Address;
use Hector\Orm\Tests\Fake\Entity\Staff;

class ManyToOneTest extends AbstractTestCase
{
    public function testConstructWithDeductionOfColumns()
    {
        $relationship = new ManyToOne('address', Staff::class, Address::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['address_id'], $relationship->getSourceColumns());
        $this->assertEquals(['address_id'], $relationship->getTargetColumns());
    }

    public function testValid()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $this->assertTrue($relationship->valid(Address::get(1)));
    }

    public function testValidWithNull()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $this->assertTrue($relationship->valid(null));
    }

    public function testValidWithBadEntity()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $this->assertFalse($relationship->valid(Staff::get(1)));
    }

    public function testValidWithCollection()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $this->assertFalse($relationship->valid(Staff::all()));
    }

    public function testSwitchIntoEntities()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $reflectionMethod = new \ReflectionMethod(ManyToOne::class, 'switchIntoEntities');
        $reflectionMethod->setAccessible(true);

        $foreigners = new Collection(
            [$address1 = Address::find(3), $address2 = Address::find(4), Actor::find(2), Actor::find(3)]
        );
        $reflectionMethod->invoke($relationship, $foreigners, $staff1 = Staff::find(1), $staff2 = Staff::find(2));

        $this->assertTrue($staff1->getRelated()->isset('address'));
        $this->assertTrue($staff2->getRelated()->isset('address'));
        $this->assertSame($address1, $staff1->getRelated()->address);
        $this->assertSame($address2, $staff2->getRelated()->address);
    }

    public function testLinkForeign()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $staff = new Staff();
        $address = new Address();
        $address->address = 'Foo';
        $address->district = 'Bar';
        $address->city_id = 1;
        $address->phone = '123456789';
        $address->location = 'POINT(0 0)';

        $relationship->linkForeign($staff, $address);

        $this->assertNotNull($address->address_id);
        $this->assertEquals($staff->address_id, $address->address_id);
    }

    public function testReverse()
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );
        $reverse = $relationship->reverse('staff');

        $this->assertInstanceOf(OneToMany::class, $reverse);
        $this->assertEquals('staff', $reverse->getName());
        $this->assertEquals($reverse->getSourceEntity(), $relationship->getTargetEntity());
        $this->assertEquals($reverse->getSourceColumns(), $relationship->getTargetColumns());
        $this->assertEquals($reverse->getTargetEntity(), $relationship->getSourceEntity());
        $this->assertEquals($reverse->getTargetColumns(), $relationship->getSourceColumns());
    }
}
