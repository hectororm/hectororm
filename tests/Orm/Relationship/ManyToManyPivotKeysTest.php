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

namespace Hector\Orm\Tests\Relationship;

use Hector\Connection\Connection;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Orm;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ManyToManyPivotKeysTest extends TestCase
{
    private Connection $connection;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $this->previousReflections = $reflections->getValue();
        $reflections->setValue(null, []);

        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->connection->execute('CREATE TABLE pivot_user (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute('CREATE TABLE pivot_role (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->execute(
            'CREATE TABLE pivot_user_role (user_id INTEGER REFERENCES pivot_user(id), '
            . 'role_id INTEGER REFERENCES pivot_role(id), note TEXT, PRIMARY KEY (user_id, role_id))',
        );
        $this->connection->execute("INSERT INTO pivot_user VALUES (1, 'u1'), (2, 'u2')");
        $this->connection->execute("INSERT INTO pivot_role VALUES (1, 'r1'), (2, 'r2'), (3, 'r3')");
        $this->connection->execute("INSERT INTO pivot_user_role VALUES (1, 1, 'old'), (2, 3, 'other')");

        new Orm($this->connection, (new Sqlite($this->connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    public static function pivotDataProvider(): iterable
    {
        yield 'source key with extra' => [['user_id' => 2, 'note' => 'new'], true];
        yield 'target key with extra' => [['role_id' => 2, 'note' => 'new'], true];
        yield 'both keys with extra' => [['user_id' => 2, 'role_id' => 2, 'note' => 'new'], true];
        yield 'keys only' => [['user_id' => 2, 'role_id' => 2], false];
    }

    /**
     * @dataProvider pivotDataProvider
     */
    public function testExistingLinkPreservesKeys(array $data, bool $hasExtra): void
    {
        $user = PivotKeyUser::find(1);
        $user->roles[0]->getPivot()->setData($data);
        $user->save();

        $this->assertSame(
            [
                ['user_id' => 1, 'role_id' => 1, 'note' => $hasExtra ? 'new' : 'old'],
                ['user_id' => 2, 'role_id' => 3, 'note' => 'other'],
            ],
            $this->rows(),
        );
    }

    /**
     * @dataProvider pivotDataProvider
     */
    public function testNewLinkPreservesKeys(array $data, bool $hasExtra): void
    {
        $role = PivotKeyUser::find(2)->roles[0];
        $role->getPivot()->setData($data);
        $user = PivotKeyUser::find(1);
        $user->roles[] = $role;
        $user->save();

        $this->assertSame(
            [
                ['user_id' => 1, 'role_id' => 1, 'note' => 'old'],
                ['user_id' => 1, 'role_id' => 3, 'note' => $hasExtra ? 'new' : null],
                ['user_id' => 2, 'role_id' => 3, 'note' => 'other'],
            ],
            $this->rows(),
        );
    }

    private function rows(): array
    {
        return $this->connection->fetchAll(
            'SELECT user_id, role_id, note FROM pivot_user_role ORDER BY user_id, role_id',
        );
    }
}

#[OrmAttribute\Table('pivot_user')]
#[OrmAttribute\BelongsToMany(
    PivotKeyRole::class,
    'roles',
    'pivot_user_role',
    ['id' => 'user_id'],
    ['role_id' => 'id'],
)]
class PivotKeyUser extends MagicEntity
{
}

#[OrmAttribute\Table('pivot_role')]
class PivotKeyRole extends MagicEntity
{
}
