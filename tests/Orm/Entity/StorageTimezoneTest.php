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

namespace Hector\Orm\Tests\Entity;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Hector\Connection\Connection;
use Hector\Connection\ConnectionSet;
use Hector\DataTypes\Type\DateTimeType;
use Hector\Orm\Attributes\Table;
use Hector\Orm\Attributes\Type;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Orm;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\SchemaContainer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class StorageTimezoneTest extends TestCase
{
    private const TABLE = 'storage_timezone_events';
    // 12:00:00 in UTC, 13:00:00 in Europe/Paris, 21:00:00 in Asia/Tokyo.
    private const INSTANT = '2026-01-15T12:00:00+00:00';

    private ?Orm $previousOrm;
    private array $previousReflections;
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $this->previousReflections = $reflections->getValue();
        $reflections->setValue(null, []);
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    private function createConnection(?string $timezone, string $name = Connection::DEFAULT_NAME): Connection
    {
        $connection = new Connection(
            'sqlite::memory:',
            name: $name,
            timezone: null === $timezone ? null : new DateTimeZone($timezone),
        );
        $connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'starts_at DATETIME NULL, ends_at DATETIME NULL, day DATE NULL)'
        );

        return $connection;
    }

    private function createOrm(Connection ...$connections): Orm
    {
        $schemas = [];
        foreach ($connections as $connection) {
            foreach ((new Sqlite($connection))->generateSchemas('main')->getSchemas() as $schema) {
                $schemas[] = $schema;
            }
        }

        return new Orm(new ConnectionSet(...$connections), new SchemaContainer($schemas));
    }

    private function stored(Connection $connection, string $column = 'starts_at', int $id = 1): ?string
    {
        return $connection->fetchOne('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', [$id])[$column];
    }

    public static function entityProvider(): array
    {
        return [
            'generic' => [StorageTimezoneEvent::class],
            'magic' => [StorageTimezoneMagicEvent::class],
        ];
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityIsStoredInTheConnectionTimezone(string $class): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);

        $entity = new $class();
        $entity->starts_at = new DateTimeImmutable(self::INSTANT);
        $entity->save();

        $this->assertSame('2026-01-15 12:00:00', $this->stored($connection));
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityIsReturnedInThePhpTimezone(string $class): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);
        $connection->execute('INSERT INTO ' . self::TABLE . " (starts_at) VALUES ('2026-01-15 12:00:00')");

        $entity = $class::find(1);

        $this->assertSame((new DateTimeImmutable(self::INSTANT))->getTimestamp(), $entity->starts_at->getTimestamp());
        $this->assertSame('Europe/Paris', $entity->starts_at->getTimezone()->getName());
        $this->assertSame('2026-01-15 13:00:00', $entity->starts_at->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityWithoutConnectionTimezoneIsUnchanged(string $class): void
    {
        $connection = $this->createConnection(null);
        $this->createOrm($connection);

        $entity = new $class();
        $entity->starts_at = new DateTimeImmutable(self::INSTANT);
        $entity->save();

        $this->assertSame('2026-01-15 13:00:00', $this->stored($connection));
        $this->assertSame('Europe/Paris', $class::find(1)->starts_at->getTimezone()->getName());
    }

    /**
     * @dataProvider entityProvider
     */
    public function testEntityIsFoundByTheQueryOfItsConnection(string $class): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);

        $entity = new $class();
        $entity->starts_at = new DateTimeImmutable('2026-01-15 13:00:00');
        $entity->save();

        // The condition is bound by the connection, the entity is written by its type.
        $found = $class::query()->where('starts_at', new DateTimeImmutable('2026-01-15 13:00:00'))->all();

        $this->assertCount(1, $found);
    }

    /**
     * @dataProvider entityProvider
     */
    public function testLoadedEntityIsNotAltered(string $class): void
    {
        $connection = $this->createConnection('UTC');
        $orm = $this->createOrm($connection);
        $connection->execute(
            'INSERT INTO ' . self::TABLE . " (starts_at, ends_at, day) "
            . "VALUES ('2026-01-15 12:00:00', '2026-01-15 14:00:00', '2026-01-15')"
        );

        $entity = $class::find(1);

        $this->assertSame([], $orm->getMapper($entity)->getEntityAlteration($entity));
    }

    /**
     * @dataProvider entityProvider
     */
    public function testDateColumnIsNotConverted(string $class): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);

        // 00:30 in Europe/Paris is the previous day in UTC.
        $entity = new $class();
        $entity->day = new DateTimeImmutable('2026-01-15 00:30:00');
        $entity->save();

        $this->assertSame('2026-01-15', $this->stored($connection, 'day'));
        $this->assertSame('2026-01-15', $class::find(1)->day->format('Y-m-d'));
    }

    public function testTypeTimezoneIsTheTimezoneOfReturnedObjects(): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);

        $entity = new StorageTimezoneEvent();
        $entity->ends_at = new DateTimeImmutable(self::INSTANT);
        $entity->save();

        // `ends_at` has a #[Type] in Asia/Tokyo.
        $this->assertSame('2026-01-15 12:00:00', $this->stored($connection, 'ends_at'));

        $connection->execute('INSERT INTO ' . self::TABLE . " (ends_at) VALUES ('2026-01-15 12:00:00')");
        $loaded = StorageTimezoneEvent::find(2);

        $this->assertSame('Asia/Tokyo', $loaded->ends_at->getTimezone()->getName());
        $this->assertSame('2026-01-15 21:00:00', $loaded->ends_at->format('Y-m-d H:i:s'));
    }

    public function testTypeTimezoneWithoutConnectionTimezoneIsUnchanged(): void
    {
        $connection = $this->createConnection(null);
        $this->createOrm($connection);

        $entity = new StorageTimezoneEvent();
        $entity->ends_at = new DateTimeImmutable(self::INSTANT);
        $entity->save();

        $this->assertSame('2026-01-15 21:00:00', $this->stored($connection, 'ends_at'));
        $this->assertSame('Asia/Tokyo', StorageTimezoneEvent::find(1)->ends_at->getTimezone()->getName());
    }

    public function testTimestampPropertyIsReadInTheConnectionTimezone(): void
    {
        $connection = $this->createConnection('UTC');
        $this->createOrm($connection);
        $connection->execute('INSERT INTO ' . self::TABLE . " (starts_at) VALUES ('2026-01-15 12:00:00')");

        $entity = StorageTimezoneTimestampEvent::find(1);

        $this->assertSame((new DateTimeImmutable(self::INSTANT))->getTimestamp(), $entity->starts_at);

        $entity->starts_at += 3600;
        $entity->save();

        $this->assertSame('2026-01-15 13:00:00', $this->stored($connection));
    }

    public function testEachConnectionHasItsStorageTimezone(): void
    {
        $utc = $this->createConnection('UTC');
        $tokyo = $this->createConnection('Asia/Tokyo', 'tokyo');
        $this->createOrm($utc, $tokyo);

        foreach ([StorageTimezoneEvent::class, StorageTimezoneTokyoEvent::class] as $class) {
            $entity = new $class();
            $entity->starts_at = new DateTimeImmutable(self::INSTANT);
            $entity->save();
        }

        $this->assertSame('2026-01-15 12:00:00', $this->stored($utc));
        $this->assertSame('2026-01-15 21:00:00', $this->stored($tokyo));
        $this->assertSame(
            StorageTimezoneEvent::find(1)->starts_at->getTimestamp(),
            StorageTimezoneTokyoEvent::find(1)->starts_at->getTimestamp()
        );
    }

    public function testTypeReplacedInTheTypeSetIsUsed(): void
    {
        $connection = $this->createConnection('UTC');
        $orm = $this->createOrm($connection);
        $connection->execute('INSERT INTO ' . self::TABLE . " (starts_at) VALUES ('2026-01-15 12:00:00')");
        $reflection = ReflectionEntity::get(StorageTimezoneMagicEvent::class);

        $this->assertInstanceOf(DateTime::class, $reflection->getType('starts_at')->fromSchema('2026-01-15 12:00:00'));
        $this->assertSame($reflection->getType('starts_at'), $reflection->getType('starts_at'));

        $orm->getTypes()->add(
            'datetime',
            new DateTimeType(class: DateTimeImmutable::class, timezone: new DateTimeZone('Asia/Tokyo'))
        );
        $date = $reflection->getType('starts_at')->fromSchema('2026-01-15 12:00:00');

        $this->assertInstanceOf(DateTimeImmutable::class, $date);
        $this->assertSame('2026-01-15 21:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testTypeSetIsNotModified(): void
    {
        $connection = $this->createConnection('UTC');
        $orm = $this->createOrm($connection);
        $type = $orm->getTypes()->get('datetime');

        $storageType = ReflectionEntity::get(StorageTimezoneMagicEvent::class)->getType('starts_at');

        $this->assertNotSame($type, $storageType);
        $this->assertSame($type, $orm->getTypes()->get('datetime'));
        $this->assertSame('2026-01-15 13:00:00', $type->toSchema(new DateTimeImmutable(self::INSTANT)));
        $this->assertSame('2026-01-15 12:00:00', $storageType->toSchema(new DateTimeImmutable(self::INSTANT)));
    }

    public function testStorageTypesAreNotSerialized(): void
    {
        $this->createOrm($this->createConnection('UTC'));
        $reflection = ReflectionEntity::get(StorageTimezoneEvent::class);
        $serialized = serialize($reflection);

        $reflection->getType('starts_at');
        $reflection->getType('ends_at');

        $this->assertSame($serialized, serialize($reflection));

        // An unserialized reflection follows the timezone of the current connection.
        Orm::$instance = null;
        $connection = $this->createConnection('Asia/Tokyo');
        $this->createOrm($connection);
        /** @var ReflectionEntity $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertSame(
            '2026-01-15 21:00:00',
            $unserialized->getType('starts_at')->toSchema(new DateTimeImmutable(self::INSTANT))
        );
    }
}

class StorageTimezoneTokyoType extends DateTimeType
{
    public function __construct()
    {
        parent::__construct(class: DateTimeImmutable::class, timezone: new DateTimeZone('Asia/Tokyo'));
    }
}

#[Table('storage_timezone_events', 'main')]
#[Type('ends_at', StorageTimezoneTokyoType::class)]
class StorageTimezoneEvent extends Entity
{
    public ?int $id = null;
    public ?DateTimeImmutable $starts_at = null;
    public ?DateTimeImmutable $ends_at = null;
    public ?DateTimeImmutable $day = null;
}

#[Table('storage_timezone_events', 'main')]
class StorageTimezoneTimestampEvent extends Entity
{
    public ?int $id = null;
    public ?int $starts_at = null;
}

#[Table('storage_timezone_events', 'main', 'tokyo')]
class StorageTimezoneTokyoEvent extends Entity
{
    public ?int $id = null;
    public ?DateTimeImmutable $starts_at = null;
}

#[Table('storage_timezone_events', 'main')]
class StorageTimezoneMagicEvent extends MagicEntity
{
}
