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

namespace Hector\Orm\Tests\Query;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Exception\MapperException;
use Hector\Orm\Exception\NotFoundException;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\Language;
use Hector\Orm\Tests\Fake\Entity\Staff;
use Hector\Query\Component\Conditions;
use Hector\Query\Component\Limit;
use Hector\Query\Component\Order;

class BuilderTest extends AbstractTestCase
{
    public function testConstruct()
    {
        $builder = new Builder(Staff::class);
        $binding = [];

        $this->assertInstanceOf(Conditions::class, $builder->where);
        $this->assertInstanceOf(Order::class, $builder->order);
        $this->assertInstanceOf(Limit::class, $builder->limit);
        $this->assertNull($builder->where->getStatement($binding));
        $this->assertNull($builder->order->getStatement($binding));
        $this->assertNull($builder->limit->getStatement($binding));
    }

    public function testWith()
    {
        $builder = new Builder(Staff::class);
        $builder->with($with = ['address' => ['city']]);

        $this->assertEquals($with, $builder->with);
    }

    public function testGetOffset0()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(0);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testGetOffset1()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(2, $entity->staff_id);
    }

    public function testGetOffsetNonexistent()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->get(99999);

        $this->assertNull($entity);
    }

    public function testGetOrFailSuccess()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrFail(0);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testGetOrFailException()
    {
        $builder = new Builder(Staff::class);

        $this->expectException(NotFoundException::class);
        $builder->getOrFail(9999);
    }

    public function testGetOrNewExistent()
    {
        $builder = new Builder(Staff::class);

        $entity = $builder->getOrNew(0);
        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNotNull($entity->staff_id);
    }

    public function testGetOrNewNonexistent()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrNew(9999);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertNull($entity->last_name);
        $this->assertNull($entity->first_name);
    }

    public function testGetOrNewWithData()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->getOrNew(9999, ['first_name' => 'Foo', 'last_name' => 'Bar']);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertEquals('Foo', $entity->first_name);
        $this->assertEquals('Bar', $entity->last_name);
    }

    public function testFindByPrimaryWithMultipleKey()
    {
        $this->expectException(MapperException::class);

        $builder = new Builder(Staff::class);
        $builder->find([99999, 1]);
    }

    public function testFindMultipleWithConditionWithTwoEntity()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 1], ['staff_id' => 2]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testFindMultipleWithConditionWithTwoEntity_onlyValues()
    {
        $builder = new Builder(Language::class);
        $collection = $builder->find(...[1, 2, 3, 4]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(4, $collection);
    }

    public function testFindMultipleWithConditionWithThreeEntityAndOneNonexistent()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 1], ['staff_id' => 2], ['staff_id' => 99999]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testFindMultipleWithNonexistentEntity()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->find(['staff_id' => 99998], ['staff_id' => 99999]);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testFind1()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testFind2()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(2);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(2, $entity->staff_id);
    }

    public function testFindOffsetNonexistent()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->find(99999);

        $this->assertNull($entity);
    }

    public function testFindOrFailSuccess()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrFail(1);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertEquals(1, $entity->staff_id);
    }

    public function testFindOrFailException()
    {
        $builder = new Builder(Staff::class);

        $this->expectException(NotFoundException::class);
        $builder->findOrFail(9999);
    }

    public function testFindOrNewExistent()
    {
        $builder = new Builder(Staff::class);

        $entity = $builder->findOrNew(1);
        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNotNull($entity->staff_id);
    }

    public function testFindOrNewNonexistent()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrNew(9999);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertNull($entity->last_name);
        $this->assertNull($entity->first_name);
    }

    public function testFindOrNewWithData()
    {
        $builder = new Builder(Staff::class);
        $entity = $builder->findOrNew(9999, ['first_name' => 'Foo', 'last_name' => 'Bar']);

        $this->assertInstanceOf(Staff::class, $entity);
        $this->assertNull($entity->staff_id);
        $this->assertEquals('Foo', $entity->first_name);
        $this->assertEquals('Bar', $entity->last_name);
    }

    public function testAll()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertGreaterThanOrEqual(2, count($collection));
    }

    public function testAllWithRelations()
    {
        $nbQueriesBefore = count($this->getOrm()->getConnection()->getLogger());
        $builder = new Builder(Staff::class);
        $collection = $builder->with(['address' => ['city' => ['country']]])->whereIn('staff_id', [1, 2])->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);

        /** @var Entity $entity */
        foreach ($collection as $entity) {
            $this->assertTrue(isset($entity->getRelated()->address));
        }

        $this->assertEquals(4, count($this->getOrm()->getConnection()->getLogger()) - $nbQueriesBefore);
    }

    public function testAllWithCondition()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->where('staff_id', '=', 1)->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(1, $collection);
    }

    public function testAllWithConditionNoResult()
    {
        $builder = new Builder(Staff::class);
        $collection = $builder->where('staff_id', '>', 9999)->all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(0, $collection);
    }

    public function testChunk()
    {
        $builder = new Builder(Film::class);
        $total = 0;
        $builder->chunk(
            50,
            function (Collection $collection) use (&$total) {
                $this->assertGreaterThan(0, count($collection));
                $this->assertLessThanOrEqual(50, count($collection));
                $this->assertContainsOnlyInstancesOf(Film::class, $collection);
                $total += count($collection);
            }
        );

        $this->assertEquals($builder->count(), $total);
    }

    public function testYield()
    {
        $builder = new Builder(Film::class);
        $iterator = $builder->yield();

        $this->assertInstanceOf(\Generator::class, $iterator);
        $this->assertEquals($builder->count(), count(iterator_to_array($iterator)));
    }

    public function testCount()
    {
        $builder = new Builder(Film::class);
        $total = $builder->count();

        $this->assertGreaterThan(0, $total);
    }
}
