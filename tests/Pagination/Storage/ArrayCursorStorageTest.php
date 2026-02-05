<?php

/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Pagination\Tests\Storage;

use Hector\Pagination\Storage\ArrayCursorStorage;
use Hector\Pagination\Storage\CursorStorageInterface;
use PHPUnit\Framework\TestCase;

class ArrayCursorStorageTest extends TestCase
{
    private ArrayCursorStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new ArrayCursorStorage();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CursorStorageInterface::class, $this->storage);
    }

    public function testStore(): void
    {
        $state = ['id' => 42, 'created_at' => '2024-01-01'];

        $name = $this->storage->store($state);

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    public function testStoreGeneratesUniqueName(): void
    {
        $name1 = $this->storage->store(['id' => 1]);
        $name2 = $this->storage->store(['id' => 2]);

        $this->assertNotSame($name1, $name2);
    }

    public function testRetrieve(): void
    {
        $state = ['id' => 42, 'foo' => 'bar'];
        $name = $this->storage->store($state);

        $retrieved = $this->storage->retrieve($name);

        $this->assertSame($state, $retrieved);
    }

    public function testRetrieveNotFound(): void
    {
        $retrieved = $this->storage->retrieve('nonexistent');

        $this->assertNull($retrieved);
    }

    public function testDelete(): void
    {
        $name = $this->storage->store(['id' => 42]);

        $this->assertNotNull($this->storage->retrieve($name));

        $this->storage->delete($name);

        $this->assertNull($this->storage->retrieve($name));
    }

    public function testDeleteNonexistent(): void
    {
        // Should not throw
        $this->storage->delete('nonexistent');

        $this->assertTrue(true);
    }

    public function testClear(): void
    {
        $this->storage->store(['id' => 1]);
        $this->storage->store(['id' => 2]);

        $this->assertSame(2, $this->storage->count());

        $this->storage->clear();

        $this->assertSame(0, $this->storage->count());
    }

    public function testCount(): void
    {
        $this->assertSame(0, $this->storage->count());

        $this->storage->store(['id' => 1]);
        $this->assertSame(1, $this->storage->count());

        $this->storage->store(['id' => 2]);
        $this->assertSame(2, $this->storage->count());
    }
}
