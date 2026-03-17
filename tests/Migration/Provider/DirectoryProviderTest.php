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

namespace Hector\Migration\Tests\Provider;

use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;
use Hector\Migration\Provider\DirectoryProvider;
use Hector\Migration\Tests\Fake\AddPostsTableMigration;
use Hector\Migration\Tests\Fake\CreateUsersTableMigration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class DirectoryProviderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../Fake/migrations';
    }

    public function testLoadMigrationsFromDirectory(): void
    {
        $provider = new DirectoryProvider($this->fixturesDir);

        $this->assertCount(3, $provider);
    }

    public function testMigrationsAreSortedAlphabetically(): void
    {
        $provider = new DirectoryProvider($this->fixturesDir);
        $keys = array_keys($provider->getArrayCopy());

        $this->assertSame([
            '20260101000000_CreateUsers',
            '20260302143000_AddPosts',
            '20260501000000_ReturnClassName',
        ], $keys);
    }

    public function testMigrationsAreCorrectInstances(): void
    {
        $provider = new DirectoryProvider($this->fixturesDir);
        $migrations = $provider->getArrayCopy();

        $this->assertInstanceOf(CreateUsersTableMigration::class, $migrations['20260101000000_CreateUsers']);
        $this->assertInstanceOf(AddPostsTableMigration::class, $migrations['20260302143000_AddPosts']);
        $this->assertInstanceOf(CreateUsersTableMigration::class, $migrations['20260501000000_ReturnClassName']);
    }

    public function testPatternFiltersNonPhpFiles(): void
    {
        // The .gitkeep file in the migrations dir should be ignored by *.php pattern
        $provider = new DirectoryProvider($this->fixturesDir);
        $keys = array_keys($provider->getArrayCopy());

        $this->assertNotContains('.gitkeep', $keys);
        $this->assertCount(3, $provider);
    }

    public function testNonExistentDirectoryThrows(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('does not exist');

        $provider = new DirectoryProvider('/nonexistent/path');
        $provider->getArrayCopy();
    }

    public function testIteratorAggregate(): void
    {
        $provider = new DirectoryProvider($this->fixturesDir);

        $ids = [];

        foreach ($provider as $id => $migration) {
            $ids[] = $id;
            $this->assertInstanceOf(MigrationInterface::class, $migration);
        }

        $this->assertCount(3, $ids);
    }

    public function testWithContainer(): void
    {
        if (false === interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('psr/container not installed');
        }

        $expectedMigration = new CreateUsersTableMigration();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->with(CreateUsersTableMigration::class)
            ->willReturn(true);
        $container->method('get')
            ->with(CreateUsersTableMigration::class)
            ->willReturn($expectedMigration);

        $provider = new DirectoryProvider(
            directory: $this->fixturesDir,
            container: $container,
        );

        $migrations = $provider->getArrayCopy();

        // The class-name-returning file should resolve via container
        $this->assertSame($expectedMigration, $migrations['20260501000000_ReturnClassName']);
    }

    public function testCaching(): void
    {
        $provider = new DirectoryProvider($this->fixturesDir);

        // First call loads, second returns cached
        $first = $provider->getArrayCopy();
        $second = $provider->getArrayCopy();

        $this->assertSame($first, $second);
    }

    public function testDepthFlatOnlyScansTopLevel(): void
    {
        $provider = new DirectoryProvider(
            directory: __DIR__ . '/../Fake/migrations_deep',
            depth: 0,
        );

        // Only 20260101000000_First.php at top level
        $this->assertCount(1, $provider);

        $keys = array_keys($provider->getArrayCopy());
        $this->assertSame(['20260101000000_First'], $keys);
    }

    public function testDepthUnlimitedScansRecursively(): void
    {
        $provider = new DirectoryProvider(
            directory: __DIR__ . '/../Fake/migrations_deep',
            depth: -1,
        );

        // First + sub/Second + sub/deep/Third
        $this->assertCount(3, $provider);

        $keys = array_keys($provider->getArrayCopy());
        $this->assertSame([
            '20260101000000_First',
            'sub/20260201000000_Second',
            'sub/deep/20260301000000_Third',
        ], $keys);
    }

    public function testDepthIntLimitsRecursion(): void
    {
        $provider = new DirectoryProvider(
            directory: __DIR__ . '/../Fake/migrations_deep',
            depth: 1,
        );

        // First + sub/Second (depth=1 means root + one level of subdirectories)
        $this->assertCount(2, $provider);

        $keys = array_keys($provider->getArrayCopy());
        $this->assertSame([
            '20260101000000_First',
            'sub/20260201000000_Second',
        ], $keys);
    }

    public function testCustomPattern(): void
    {
        $provider = new DirectoryProvider(
            directory: $this->fixturesDir,
            pattern: '*CreateUsers*',
        );

        $this->assertCount(1, $provider);

        $keys = array_keys($provider->getArrayCopy());
        $this->assertSame(['20260101000000_CreateUsers'], $keys);
    }
}
