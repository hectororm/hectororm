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

namespace Hector\Orm\Tests\Storage;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Tests\Fake\Entity\Film;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class EntityStorageTest extends TestCase
{
    public function testSerialize()
    {
        $this->expectException(OrmException::class);

        $storage = new FakeEntityStorage();
        serialize($storage);
    }

    public function testGetIterator()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());
        $storage->attach($entity2 = new Film());

        $this->assertIsIterable($storage->getIterator());
        $this->assertCount(2, $storage->getIterator());
    }

    public function testDetach()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());
        $storage->attach($entity2 = new Film());

        $this->assertCount(2, $storage);

        $storage->detach($entity);

        $this->assertCount(1, $storage);
    }

    public function testDetachCollection()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());
        $storage->attach($entity2 = new Film());
        $storage->attach($entity3 = new Film());

        $this->assertCount(3, $storage);

        $storage->detach(new Collection([$entity2, $entity3]));

        $this->assertCount(1, $storage);
    }

    public function testUnset()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());

        $this->assertCount(1, $storage);

        unset($storage[$entity]);

        $this->assertCount(0, $storage);
    }

    public function testDetachUnknownEntity()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());
        $entity2 = new Film();

        $this->assertCount(1, $storage);

        $storage->detach($entity2);

        $this->assertCount(1, $storage);
    }

    public function testIterator()
    {
        $entities = [];
        $storage = new FakeEntityStorage();
        $storage->attach($entities[] = new Film());
        $storage->attach($entities[] = new Film());
        $storage->attach($entities[] = new Film());
        $storage->attach($entities[] = new Film());
        $storage->attach($entities[] = new Film());

        $this->assertCount(5, $storage);
    }

    public function testAttach()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity1 = new Film());

        $this->assertCount(1, $storage);

        $storage->attach($entity2 = new Film());
        $this->assertCount(2, $storage);

        $storage->attach($entity1);
        $this->assertCount(2, $storage);
    }

    public function testAttachCollection()
    {
        $storage = new FakeEntityStorage();
        $collection = new Collection(
            [
                $entity1 = new Film(),
                $entity2 = new Film(),
            ]
        );
        $storage->attach($collection);
        $storage->attach($entity3 = new Film());

        $this->assertCount(3, $storage);
    }

    public function testContainsKnownEntity()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());

        $this->assertTrue($storage->contains($entity));
        $this->assertTrue(isset($storage[$entity]));
    }

    public function testContainsCollection()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());
        $storage->attach($entity2 = new Film());
        $storage->attach($entity3 = new Film());

        $this->assertTrue($storage->contains(new Collection([$entity2, $entity3])));
        $this->assertFalse($storage->contains(new Collection([$entity2, new Film()])));
    }

    public function testContainsUnknownEntity()
    {
        $storage = new FakeEntityStorage();
        $entity = new Film();

        $this->assertFalse($storage->contains($entity));
        $this->assertFalse(isset($storage[$entity]));
    }

    public function testContainsSimilar()
    {
        $storage = new FakeEntityStorage();
        $entity = new Film();
        $entity->title = 'Title of article';
        $entity->content = 'Content of article';
        $storage->attach($entity);
        unset($entity);

        $entity2 = new Film();
        $entity2->title = 'Title of article';
        $entity2->content = 'Content of article';

        $this->assertFalse($storage->contains($entity2));
        $this->assertFalse(isset($storage[$entity2]));
    }

    public function testCount()
    {
        $entities = [];
        $storage = new FakeEntityStorage();
        $storage->attach($entities[] = new Film());
        $storage->attach($entities[] = new Film());
        $storage->attach(new Collection([$entities[] = new Film(), $entities[] = new Film()]));

        $this->assertCount(4, $storage);

        unset($entities[0]);

        $this->assertCount(3, $storage);
    }

    public function testStatusWithTwoAttachment()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());

        $this->assertEquals(FakeEntityStorage::STATUS_NONE, $storage[$entity]);

        $storage->attach($entity, FakeEntityStorage::STATUS_TO_UPDATE);

        $this->assertEquals(FakeEntityStorage::STATUS_TO_UPDATE, $storage[$entity]);
    }

    public function testStatusWithUnknownEntity()
    {
        $storage = new FakeEntityStorage();

        $this->expectException(UnexpectedValueException::class);

        $storage[new Film()];
    }

    public function testStatusGetSet()
    {
        $storage = new FakeEntityStorage();
        $storage->attach($entity = new Film());

        $this->assertEquals(FakeEntityStorage::STATUS_NONE, $storage[$entity]);

        $storage[$entity] = FakeEntityStorage::STATUS_TO_DELETE;

        $this->assertEquals(FakeEntityStorage::STATUS_TO_DELETE, $storage[$entity]);
    }
}
