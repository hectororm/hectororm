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

namespace Hector\Orm\Tests\Entity;

use Generator;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\NotFoundException;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Query\Builder;
use Hector\Orm\Tests\AbstractTestCase;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Customer;
use Hector\Orm\Tests\Fake\Entity\Film;
use Hector\Orm\Tests\Fake\Entity\FilmMagic;
use Hector\Orm\Tests\Fake\Entity\Language;
use Hector\Orm\Tests\Fake\Entity\Payment;
use ReflectionProperty;

class EntityTest extends AbstractTestCase
{
    public function classProvider()
    {
        return [[Film::class], [FilmMagic::class]];
    }

    /**
     * @dataProvider classProvider
     */
    public function testEntityGetSuccess($class): void
    {
        $entity = $class::get(1);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(2, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testEntityGetNull($class): void
    {
        $entity = $class::get(9999);

        $this->assertNull($entity);
    }

    /**
     * @dataProvider classProvider
     */
    public function testGetOrFail($class): void
    {
        $entity = $class::getOrFail(1);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(2, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testGetOrFailWithFailure($class): void
    {
        $this->expectException(NotFoundException::class);

        $class::getOrFail(9999);
    }

    /**
     * @dataProvider classProvider
     */
    public function testGetOrNew($class): void
    {
        $entity = $class::getOrNew(1);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(2, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testGetOrNewWithData($class): void
    {
        $entity = $class::getOrNew(9999, ['title' => 'New title']);

        $this->assertInstanceOf($class, $entity);
        $this->assertNull($entity->film_id);
        $this->assertEquals('New title', $entity->title);
    }

    /**
     * @dataProvider classProvider
     */
    public function testGetOrNewWithEmptyData($class): void
    {
        $entity = $class::getOrNew(9999);

        $this->assertInstanceOf($class, $entity);
        $this->assertNull($entity->film_id);
        $this->assertNull($entity->title);
    }

    /**
     * @dataProvider classProvider
     */
    public function testEntityFindSuccess($class): void
    {
        $entity = $class::find(10);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(10, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testEntityFindNull($class): void
    {
        $entity = $class::find(9999);

        $this->assertNull($entity);
    }

    /**
     * @dataProvider classProvider
     */
    public function testFindOrFail($class): void
    {
        $entity = $class::findOrFail(10);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(10, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testFindOrFailWithFailure($class): void
    {
        $this->expectException(NotFoundException::class);

        $class::findOrFail(9999);
    }

    /**
     * @dataProvider classProvider
     */
    public function testFindOrNew($class): void
    {
        $entity = $class::findOrNew(1);

        $this->assertInstanceOf($class, $entity);
        $this->assertEquals(1, $entity->film_id);
    }

    /**
     * @dataProvider classProvider
     */
    public function testFindOrNewWithData($class): void
    {
        $entity = $class::findOrNew(9999, ['title' => 'New title']);

        $this->assertInstanceOf($class, $entity);
        $this->assertNull($entity->film_id);
        $this->assertEquals('New title', $entity->title);
    }

    /**
     * @dataProvider classProvider
     */
    public function testFindOrNewWithEmptyData($class): void
    {
        $entity = $class::findOrNew(9999);

        $this->assertInstanceOf($class, $entity);
        $this->assertNull($entity->film_id);
        $this->assertNull($entity->title);
    }

    /**
     * @dataProvider classProvider
     */
    public function testAll($class): void
    {
        $collection = $class::all();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertGreaterThan(1, count($collection));
        $this->assertContainsOnlyInstancesOf($class, $collection);
    }

    /**
     * @dataProvider classProvider
     */
    public function testChunk($class): void
    {
        $total = 0;
        $class::chunk(
            50,
            function (Collection $collection) use ($class, &$total): void {
                $this->assertGreaterThan(0, count($collection));
                $this->assertLessThanOrEqual(50, count($collection));
                $this->assertContainsOnlyInstancesOf($class, $collection);
                $total += count($collection);
            }
        );

        $this->assertEquals($class::count(), $total);
    }

    /**
     * @dataProvider classProvider
     */
    public function testYield($class): void
    {
        $iterator = call_user_func([$class, 'yield']);

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertEquals($class::count(), count(iterator_to_array($iterator)));
    }

    /**
     * @dataProvider classProvider
     */
    public function testCount($class): void
    {
        $total = $class::count();

        $this->assertGreaterThan(0, $total);
    }

    /**
     * @dataProvider classProvider
     */
    public function testEntityBuilder($class): void
    {
        $entityBuilder = $class::query();

        $this->assertInstanceOf(Builder::class, $entityBuilder);

        $reflectionProperty = new ReflectionProperty(Builder::class, 'entityReflection');
        $reflectionProperty->setAccessible(true);
        /** @var ReflectionEntity $entityReflection */
        $entityReflection = $reflectionProperty->getValue($entityBuilder);

        $this->assertEquals($class, $entityReflection->class);
    }

    /**
     * @dataProvider classProvider
     */
    public function testSaveNew($class): void
    {
        $film = new $class();
        $film->language_id = 1;
        $film->title = 'Hector Film';
        $film->description = 'Hector Film Description';

        $this->assertNull($film->film_id);
        $this->assertNull($film->last_update);

        $film->save();

        $this->assertNotNull($film->film_id);
        $this->assertNotNull($film->last_update);
    }

    /**
     * @dataProvider classProvider
     */
    public function testSaveNewWithNewRelation($class): void
    {
        $language = new Language();
        $language->name = 'Foo bar';

        $film = new $class();
        $film->language_id = 1;
        $film->title = 'Hector Film';
        $film->description = 'Hector Film Description';
        $film->getRelated()->original_language = $language;

        $this->assertNull($film->film_id);
        $this->assertNull($film->last_update);

        $film->save();

        $this->assertNotNull($film->film_id);
        $this->assertNotNull($film->last_update);
        $this->assertNotNull($film->getRelated()->original_language->language_id);
        $this->assertNotNull($film->getRelated()->original_language->last_update);
    }

    public function testSaveWithRelationOneToMany(): void
    {
        $payment = new Payment();
        $payment->payment_date = '2025-09-19';
        $payment->staff_id = 1;
        $payment->rental_id = 1;
        $payment->amount = 100;

        $customer = Customer::find(2);
        $customer->payments->append($payment);
        $customer->save();
        $payment->refresh();

        $this->assertNotNull($payment->payment_id);
        $this->assertEquals(100, $payment->amount);

        $payment->amount = 200;
        $customer->save();
        $payment->refresh();

        $this->assertEquals(100, $payment->amount);
    }

    public function testSaveWithExistentRelationOneToMany(): void
    {
        $customer = Customer::find(1);
        $payment = $customer->payments->first(fn(Payment $payment): bool => 5 === $payment->payment_id);

        $amount = $payment->amount;

        $this->assertFalse($payment->isAltered());
        $payment->amount += 1;
        $this->assertTrue($payment->isAltered());
        $customer->save();
        $this->assertTrue($payment->isAltered());
        $customer->save(true);
        $this->assertFalse($payment->isAltered());

        $payment->refresh();
        $this->assertEquals(round($amount + 1), round($payment->amount));
    }

    /**
     * @dataProvider classProvider
     */
    public function testDeleteExistent($class): void
    {
        $film = new $class();
        $film->language_id = 1;
        $film->title = 'Hector Film';
        $film->description = 'Hector Film Description';
        $film->save();

        $this->assertNotNull($film->film_id);

        $film->delete();
        $this->assertNull($class::get($film->film_id));
    }

    /**
     * @dataProvider classProvider
     */
    public function testDeleteNonexistent($class): void
    {
        $this->expectException(OrmException::class);

        $film = new $class();
        $film->delete();
    }

    /**
     * @dataProvider classProvider
     */
    public function testRefresh($class): void
    {
        $film = $class::get(1);
        $originalTitle = $film->title;
        $film->title = 'Foo bar';

        $this->assertEquals('Foo bar', $film->title);

        $film->refresh();

        $this->assertEquals($originalTitle, $film->title);
    }

    /**
     * @dataProvider classProvider
     */
    public function testRefreshNonexistent($class): void
    {
        $this->expectException(OrmException::class);

        $film = new $class();
        $film->refresh();
    }

    /**
     * @dataProvider classProvider
     */
    public function testIsEqualTo($class): void
    {
        $film1 = $class::get(1);
        $film1bis = $class::get(1);
        $film2 = $class::get(2);
        $film2bis = $class::get(2);

        $actor = Actor::get();

        $this->assertTrue($film1->isEqualTo($film1bis));
        $this->assertFalse($film1->isEqualTo($film2bis));
        $this->assertTrue($film2->isEqualTo($film2bis));
        $this->assertFalse($film2->isEqualTo($film1bis));

        $this->assertFalse($film1->isEqualTo($actor));
        $this->assertFalse($film2->isEqualTo($actor));
        $this->assertFalse($actor->isEqualTo($film1));
        $this->assertFalse($actor->isEqualTo($film2));
    }

    /**
     * @dataProvider classProvider
     */
    public function testIsAltered($class): void
    {
        /** @var Film $film */
        $film = $class::get(1);

        $this->assertFalse($film->isAltered());
        $this->assertFalse($film->isAltered('rating'));
        $this->assertFalse($film->isAltered('description'));

        $film->description = 'Foo bar';

        $this->assertTrue($film->isAltered());
        $this->assertFalse($film->isAltered('rating'));
        $this->assertTrue($film->isAltered('description'));
        $this->assertTrue($film->isAltered('description', 'rating'));

        $film->refresh();

        $this->assertFalse($film->isAltered());
        $this->assertFalse($film->isAltered('rating'));
        $this->assertFalse($film->isAltered('description'));
    }
}
