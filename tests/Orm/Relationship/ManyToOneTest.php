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
use Hector\Orm\Relationship\ManyToOne;
use Hector\Orm\Relationship\OneToMany;
use Hector\Orm\Relationship\Relationship;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Address;
use Hector\Orm\Tests\Fake\Entity\Staff;
use ReflectionMethod;

class ManyToOneTest extends AbstractTestCase
{
    public function testConstructWithDeductionOfColumns(): void
    {
        $relationship = new ManyToOne('address', Staff::class, Address::class);

        $this->assertInstanceOf(Relationship::class, $relationship);
        $this->assertEquals(['address_id'], $relationship->getSourceColumns());
        $this->assertEquals(['address_id'], $relationship->getTargetColumns());
    }

    public function testValid(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $value = Address::get(1);
        $this->assertTrue($relationship->valid($value));
    }

    public function testValidWithNull(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $value = null;
        $this->assertTrue($relationship->valid($value));
    }

    public function testValidAcceptsSubclassOfTarget(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        // A subclass of the target entity is still a valid related entity (instanceof, not a
        // strict class match).
        $value = new class extends Address {
        };

        $this->assertTrue($relationship->valid($value));
    }

    public function testValidWithBadEntity(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $value = Staff::get(1);
        $this->assertFalse($relationship->valid($value));
    }

    public function testValidWithCollection(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $value = Staff::all();
        $this->assertFalse($relationship->valid($value));
    }

    public function testSwitchIntoEntities(): void
    {
        $relationship = new ManyToOne(
            'address',
            Staff::class,
            Address::class,
            ['address_id' => 'address_id']
        );

        $reflectionMethod = new ReflectionMethod(ManyToOne::class, 'switchIntoEntities');
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

    public function testLinkForeign(): void
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

    public function testReverse(): void
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

    public function testEntityGet(): void
    {
        $staff = Staff::findOrFail(1);

        $this->assertInstanceOf(Address::class, $staff->address);
        $this->assertSame(3, $staff->address_id);
        $this->assertSame(3, $staff->address->address_id);
    }

    /**
     * @dataProvider keysMatchProvider
     */
    public function testKeysMatch(array $left, array $right, bool $expected): void
    {
        $method = new ReflectionMethod(Relationship::class, 'keysMatch');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke(null, $left, $right));
    }

    public function keysMatchProvider(): array
    {
        return [
            // int vs its string representation must still match (PDO string tolerance)
            'int vs string' => [[5], ['5'], true],
            'same int' => [[5], [5], true],
            // numeric-looking strings must NOT be coerced
            'zero-padded' => [['01'], ['1'], false],
            'scientific' => [['1e2'], ['100'], false],
            // null handling: null never matches 0/empty, only another null
            'null vs zero' => [[null], [0], false],
            'null vs empty' => [[null], [''], false],
            'null vs null' => [[null], [null], true],
            // composite keys
            'composite match' => [[1, '2'], ['1', 2], true],
            'composite mismatch' => [[1, '01'], [1, '1'], false],
            'different arity' => [[1], [1, 2], false],
        ];
    }
}
