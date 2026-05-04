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

use Hector\Migration\Tests\Fake\Psr4\NotAMigration;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;
use Hector\Migration\Provider\Psr4Provider;
use Hector\Migration\Tests\Fake\Psr4\AddPosts;
use Hector\Migration\Tests\Fake\Psr4\CreateUsers;
use Hector\Migration\Tests\Fake\Psr4\NoAttribute;
use Hector\Migration\Tests\Fake\Psr4\Sub\CreateComments;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class Psr4ProviderTest extends TestCase
{
    private string $psr4Dir;

    protected function setUp(): void
    {
        $this->psr4Dir = __DIR__ . '/../Fake/Psr4';
    }

    public function testLoadMigrations(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        // Default depth=-1 (unlimited): AddPosts, CreateUsers, NoAttribute, Sub\CreateComments
        // NotAMigration is skipped
        $this->assertCount(4, $provider);

        $migrations = $provider->getArrayCopy();

        foreach ($migrations as $migration) {
            $this->assertInstanceOf(MigrationInterface::class, $migration);
        }
    }

    public function testFqcnAsId(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $ids = array_keys($provider->getArrayCopy());

        $this->assertContains(CreateUsers::class, $ids);
        $this->assertContains(AddPosts::class, $ids);
        $this->assertContains(NoAttribute::class, $ids);
        $this->assertContains(CreateComments::class, $ids);
    }

    public function testSkipsNonMigrationClasses(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $ids = array_keys($provider->getArrayCopy());

        $this->assertNotContains(NotAMigration::class, $ids);
    }

    public function testSortedAlphabeticallyByFqcn(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $ids = array_keys($provider->getArrayCopy());

        // Sorted alphabetically by file path (which maps to FQCN order)
        $this->assertSame([
            AddPosts::class,
            CreateUsers::class,
            NoAttribute::class,
            CreateComments::class,
        ], $ids);
    }

    public function testDepthFlatLimitsToTopLevel(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
            depth: 0,
        );

        // depth=0: only top-level files
        $this->assertCount(3, $provider);

        $ids = array_keys($provider->getArrayCopy());

        $this->assertNotContains(CreateComments::class, $ids);
    }

    public function testCorrectInstances(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $migrations = $provider->getArrayCopy();

        $this->assertInstanceOf(CreateUsers::class, $migrations[CreateUsers::class]);
        $this->assertInstanceOf(AddPosts::class, $migrations[AddPosts::class]);
        $this->assertInstanceOf(NoAttribute::class, $migrations[NoAttribute::class]);
        $this->assertInstanceOf(CreateComments::class,
            $migrations[CreateComments::class]);
    }

    public function testNonExistentDirectoryThrows(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('does not exist');

        $provider = new Psr4Provider(
            namespace: 'App\\Migration',
            directory: '/nonexistent/path',
        );

        $provider->getArrayCopy();
    }

    public function testWithContainer(): void
    {
        if (false === interface_exists(ContainerInterface::class)) {
            $this->markTestSkipped('psr/container not installed');
        }

        $expectedMigration = new CreateUsers();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(fn(string $id): bool => $id === CreateUsers::class);
        $container->method('get')
            ->with(CreateUsers::class)
            ->willReturn($expectedMigration);

        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
            container: $container,
        );

        $migrations = $provider->getArrayCopy();

        $this->assertSame($expectedMigration, $migrations[CreateUsers::class]);
    }

    public function testIteratorAggregate(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $ids = [];

        foreach ($provider as $id => $migration) {
            $ids[] = $id;
            $this->assertInstanceOf(MigrationInterface::class, $migration);
        }

        $this->assertCount(4, $ids);
    }

    public function testCaching(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
        );

        $first = $provider->getArrayCopy();
        $second = $provider->getArrayCopy();

        $this->assertSame($first, $second);
    }

    public function testCustomPattern(): void
    {
        $provider = new Psr4Provider(
            namespace: 'Hector\\Migration\\Tests\\Fake\\Psr4',
            directory: $this->psr4Dir,
            pattern: 'Create*.php',
        );

        $ids = array_keys($provider->getArrayCopy());

        $this->assertContains(CreateUsers::class, $ids);
        $this->assertContains(CreateComments::class, $ids);
        $this->assertNotContains(AddPosts::class, $ids);
        $this->assertNotContains(NoAttribute::class, $ids);
    }
}
