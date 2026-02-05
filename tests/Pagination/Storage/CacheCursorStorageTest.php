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

use DateInterval;
use Hector\Pagination\Storage\CacheCursorStorage;
use Hector\Pagination\Storage\CursorStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CacheCursorStorageTest extends TestCase
{
    private CacheInterface $cache;
    private CacheCursorStorage $storage;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->storage = new CacheCursorStorage($this->cache);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(CursorStorageInterface::class, $this->storage);
    }

    public function testStore(): void
    {
        $state = ['id' => 42];

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->matchesRegularExpression('/^hector_cursor_[a-f0-9]{32}$/'),
                $state,
                null
            );

        $name = $this->storage->store($state);

        $this->assertIsString($name);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $name);
    }

    public function testStoreWithTtl(): void
    {
        $state = ['id' => 42];
        $ttl = 3600;

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $state,
                $ttl
            );

        $this->storage->store($state, $ttl);
    }

    public function testStoreWithDateIntervalTtl(): void
    {
        $state = ['id' => 42];
        $ttl = new DateInterval('PT1H');

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $state,
                $ttl
            );

        $this->storage->store($state, $ttl);
    }

    public function testStoreWithDefaultTtl(): void
    {
        $defaultTtl = 7200;
        $storage = new CacheCursorStorage($this->cache, $defaultTtl);
        $state = ['id' => 42];

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $state,
                $defaultTtl
            );

        $storage->store($state);
    }

    public function testStoreOverridesDefaultTtl(): void
    {
        $defaultTtl = 7200;
        $customTtl = 3600;
        $storage = new CacheCursorStorage($this->cache, $defaultTtl);
        $state = ['id' => 42];

        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $state,
                $customTtl
            );

        $storage->store($state, $customTtl);
    }

    public function testRetrieve(): void
    {
        $state = ['id' => 42, 'foo' => 'bar'];
        $name = 'abc123';

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->with('hector_cursor_' . $name)
            ->willReturn($state);

        $retrieved = $this->storage->retrieve($name);

        $this->assertSame($state, $retrieved);
    }

    public function testRetrieveNotFound(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $retrieved = $this->storage->retrieve('nonexistent');

        $this->assertNull($retrieved);
    }

    public function testRetrieveInvalidData(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn('not-an-array');

        $retrieved = $this->storage->retrieve('invalid');

        $this->assertNull($retrieved);
    }

    public function testDelete(): void
    {
        $name = 'abc123';

        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('hector_cursor_' . $name);

        $this->storage->delete($name);
    }
}
