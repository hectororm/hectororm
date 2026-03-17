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
use Hector\Migration\Provider\ArrayProvider;
use Hector\Migration\Tests\Fake\AddPostsTableMigration;
use Hector\Migration\Tests\Fake\CreateUsersTableMigration;
use PHPUnit\Framework\TestCase;

class ArrayProviderTest extends TestCase
{
    public function testEmptyProvider(): void
    {
        $provider = new ArrayProvider();

        $this->assertCount(0, $provider);
        $this->assertEmpty($provider->getArrayCopy());
    }

    public function testConstructWithMigrations(): void
    {
        $m1 = new CreateUsersTableMigration();
        $m2 = new AddPostsTableMigration();

        $provider = new ArrayProvider([
            'create_users' => $m1,
            'add_posts' => $m2,
        ]);

        $this->assertCount(2, $provider);
        $this->assertSame($m1, $provider->getArrayCopy()['create_users']);
        $this->assertSame($m2, $provider->getArrayCopy()['add_posts']);
    }

    public function testAddWithExplicitId(): void
    {
        $provider = new ArrayProvider();
        $m1 = new CreateUsersTableMigration();

        $result = $provider->add($m1, 'create_users');

        $this->assertSame($provider, $result);
        $this->assertCount(1, $provider);
        $this->assertSame($m1, $provider->getArrayCopy()['create_users']);
    }

    public function testAddDefaultsToFqcn(): void
    {
        $provider = new ArrayProvider();
        $m1 = new CreateUsersTableMigration();

        $provider->add($m1);

        $this->assertCount(1, $provider);
        $this->assertArrayHasKey(CreateUsersTableMigration::class, $provider->getArrayCopy());
    }

    public function testAddDuplicateIdThrows(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Duplicate migration identifier');

        $provider = new ArrayProvider();
        $provider->add(new CreateUsersTableMigration(), 'create_users');
        $provider->add(new AddPostsTableMigration(), 'create_users');
    }

    public function testAddDuplicateFqcnThrows(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Duplicate migration identifier');

        $provider = new ArrayProvider();
        $provider->add(new CreateUsersTableMigration());
        $provider->add(new CreateUsersTableMigration());
    }

    public function testIteratorAggregate(): void
    {
        $m1 = new CreateUsersTableMigration();
        $m2 = new AddPostsTableMigration();

        $provider = new ArrayProvider([
            'create_users' => $m1,
            'add_posts' => $m2,
        ]);

        $keys = [];
        foreach ($provider as $id => $migration) {
            $keys[] = $id;
        }

        $this->assertSame(['create_users', 'add_posts'], $keys);
    }
}
