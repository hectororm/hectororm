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

namespace Hector\Connection\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use PDO;
use PHPUnit\Framework\TestCase;

class ConnectionDateTimeBindTest extends TestCase
{
    private const TABLE = 'hector_datetime_bind_test';

    private ?Connection $connection = null;
    private string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);

        if (null !== $this->connection) {
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        }
        $this->connection = null;
    }

    public static function driverProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    private function getConnection(
        string $driver = 'sqlite',
        string $dateTimeFormat = Connection::DEFAULT_DATETIME_FORMAT,
        ?DateTimeZone $timezone = null,
        ?Logger $logger = null,
    ): Connection {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required');
            }
        }

        $this->connection = new Connection(
            $dsn,
            logger: $logger,
            dateTimeFormat: $dateTimeFormat,
            timezone: $timezone,
        );
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY, d DATETIME(6) NULL)'
        );

        return $this->connection;
    }

    private function insert(Connection $connection, int $id, mixed $date): void
    {
        $connection->execute('INSERT INTO ' . self::TABLE . ' (id, d) VALUES (?, ?)', [$id, $date]);
    }

    private function stored(Connection $connection, int $id = 1): ?string
    {
        return $connection->fetchOne('SELECT d FROM ' . self::TABLE . ' WHERE id = ?', [$id])['d'] ?? null;
    }

    public function testDefaults(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertSame('Y-m-d H:i:s', $connection->getDateTimeFormat());
        $this->assertNull($connection->getTimezone());
    }

    public function testGetters(): void
    {
        $timezone = new DateTimeZone('UTC');
        $connection = new Connection('sqlite::memory:', dateTimeFormat: 'Y-m-d H:i:s.u', timezone: $timezone);

        $this->assertSame('Y-m-d H:i:s.u', $connection->getDateTimeFormat());
        $this->assertSame($timezone, $connection->getTimezone());
    }

    public static function dateProvider(): array
    {
        $data = [];
        foreach (array_keys(self::driverProvider()) as $driver) {
            $data[$driver . ' immutable'] = [$driver, DateTimeImmutable::class];
            $data[$driver . ' mutable'] = [$driver, DateTime::class];
        }

        return $data;
    }

    /**
     * @dataProvider dateProvider
     *
     * @param class-string<DateTimeInterface> $class
     */
    public function testBindDateTime(string $driver, string $class): void
    {
        $connection = $this->getConnection($driver);
        $date = new $class('2026-01-02 03:04:05');

        $this->insert($connection, 1, $date);

        $this->assertSame('2026-01-02 03:04:05', substr((string)$this->stored($connection), 0, 19));
        $this->assertSame('2026-01-02 03:04:05', $date->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testBindDateTimeInCondition(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $this->insert($connection, 1, '2026-01-01 00:00:00');
        $this->insert($connection, 2, '2026-01-03 00:00:00');

        $ids = $connection->fetchColumn(
            'SELECT id FROM ' . self::TABLE . ' WHERE d < :date ORDER BY id',
            ['date' => new DateTimeImmutable('2026-01-02 00:00:00')]
        );

        $this->assertEquals([1], $ids);
    }

    public function testBindDateTimeWithNamedParameter(): void
    {
        $connection = $this->getConnection();

        $connection->execute(
            'INSERT INTO ' . self::TABLE . ' (id, d) VALUES (:id, :date)',
            ['id' => 1, 'date' => new DateTimeImmutable('2026-01-02 03:04:05')]
        );

        $this->assertSame('2026-01-02 03:04:05', $this->stored($connection));
    }

    public function testBindDateTimeWithBindParamList(): void
    {
        $connection = $this->getConnection();
        $binds = new BindParamList();
        $binds->add(1, 'id');
        $binds->add(new DateTimeImmutable('2026-01-02 03:04:05'), 'date');

        $connection->execute('INSERT INTO ' . self::TABLE . ' (id, d) VALUES (:id, :date)', $binds);

        $this->assertSame('2026-01-02 03:04:05', $this->stored($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testBindDateTimeWithCustomFormat(string $driver): void
    {
        $connection = $this->getConnection($driver, dateTimeFormat: 'Y-m-d H:i:s.u');

        $this->insert($connection, 1, new DateTimeImmutable('2026-01-02 03:04:05.123456'));

        $this->assertSame('2026-01-02 03:04:05.123456', $this->stored($connection));
    }

    public function testBindDateTimeIsConvertedToConnectionTimezone(): void
    {
        $connection = $this->getConnection(timezone: new DateTimeZone('UTC'));

        $this->insert($connection, 1, new DateTimeImmutable('2026-01-02 03:04:05+09:00'));
        $this->insert($connection, 2, new DateTimeImmutable('2026-07-02 03:04:05'));

        $this->assertSame('2026-01-01 18:04:05', $this->stored($connection, 1));
        $this->assertSame('2026-07-02 01:04:05', $this->stored($connection, 2));
    }

    public function testBindDateTimeIsConvertedToDefaultTimezoneWithoutConnectionTimezone(): void
    {
        $connection = $this->getConnection();

        $this->insert($connection, 1, new DateTimeImmutable('2026-01-02 03:04:05+09:00'));
        $this->insert($connection, 2, new DateTimeImmutable('2026-01-02 03:04:05', new DateTimeZone('UTC')));
        $this->insert($connection, 3, new DateTimeImmutable('@1767323045'));

        $this->assertSame('2026-01-01 19:04:05', $this->stored($connection, 1));
        $this->assertSame('2026-01-02 04:04:05', $this->stored($connection, 2));
        $this->assertSame('2026-01-02 04:04:05', $this->stored($connection, 3));
    }

    public function testBindStringableDateTimeUsesConnectionFormatAndTimezone(): void
    {
        $connection = $this->getConnection(timezone: new DateTimeZone('UTC'));
        $date = new class ('2026-01-02 03:04:05+09:00') extends DateTimeImmutable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        $this->insert($connection, 1, $date);

        $this->assertSame('2026-01-01 18:04:05', $this->stored($connection));
    }

    public function testBindParamListIsNotModified(): void
    {
        $connection = $this->getConnection();
        $date = new DateTimeImmutable('2026-01-02 03:04:05');
        $binds = new BindParamList();
        $binds->add(1, 'id');
        $bindParam = $binds->add($date, 'date');

        $connection->execute('INSERT INTO ' . self::TABLE . ' (id, d) VALUES (:id, :date)', $binds);

        $this->assertCount(2, $binds);
        $this->assertSame($bindParam, $binds->getArrayCopy()['date']);
        $this->assertSame($date, $bindParam->getValue());
    }

    public function testBindNullAndStringAreNotConverted(): void
    {
        $connection = $this->getConnection(dateTimeFormat: 'Y-m-d', timezone: new DateTimeZone('UTC'));

        $this->insert($connection, 1, null);
        $this->insert($connection, 2, '2026-01-02 03:04:05+09:00');

        $this->assertNull($this->stored($connection, 1));
        $this->assertSame('2026-01-02 03:04:05+09:00', $this->stored($connection, 2));
    }

    public function testBindDateTimeKeepsExplicitDataType(): void
    {
        $logger = new Logger();
        $connection = $this->getConnection(dateTimeFormat: 'U', timezone: new DateTimeZone('UTC'), logger: $logger);
        $binds = new BindParamList();
        $binds->add(new BindParam('date', new DateTimeImmutable('@1767323045'), PDO::PARAM_INT));

        $connection->execute('INSERT INTO ' . self::TABLE . ' (id, d) VALUES (1, :date)', $binds);

        $logs = $logger->getLogs();
        $bindParam = end($logs)->getParameters()->getArrayCopy()['date'];
        $this->assertSame('1767323045', $bindParam->getValue());
        $this->assertSame(PDO::PARAM_INT, $bindParam->getDataType());
    }

    public function testLoggerReceivesConvertedValues(): void
    {
        $logger = new Logger();
        $connection = $this->getConnection(timezone: new DateTimeZone('UTC'), logger: $logger);

        $this->insert($connection, 7, new DateTimeImmutable('2026-01-02 03:04:05+09:00'));

        $logs = $logger->getLogs();
        $parameters = end($logs)->getParameters()->getArrayCopy();
        $this->assertSame([1, 2], array_keys($parameters));
        $this->assertSame(7, $parameters[1]->getValue());
        $this->assertSame('2026-01-01 18:04:05', $parameters[2]->getValue());
    }

    public function testLoggerReceivesSameListWithoutDateTime(): void
    {
        $logger = new Logger();
        $connection = $this->getConnection(logger: $logger);
        $binds = new BindParamList([1, '2026-01-02 03:04:05']);

        $connection->execute('INSERT INTO ' . self::TABLE . ' (id, d) VALUES (?, ?)', $binds);

        $logs = $logger->getLogs();
        $this->assertSame($binds, end($logs)->getParameters());
    }

    public function testFromPdo(): void
    {
        $timezone = new DateTimeZone('UTC');
        $this->connection = Connection::fromPdo(
            new PDO('sqlite::memory:'),
            dateTimeFormat: 'Y-m-d\TH:i:sP',
            timezone: $timezone,
        );
        $this->connection->execute('CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY, d TEXT NULL)');

        $this->insert($this->connection, 1, new DateTimeImmutable('2026-01-02 03:04:05+09:00'));

        $this->assertSame('Y-m-d\TH:i:sP', $this->connection->getDateTimeFormat());
        $this->assertSame($timezone, $this->connection->getTimezone());
        $this->assertSame('2026-01-01T18:04:05+00:00', $this->stored($this->connection));
    }

    public function testFromPdoDefaults(): void
    {
        $connection = Connection::fromPdo(new PDO('sqlite::memory:'));

        $this->assertSame('Y-m-d H:i:s', $connection->getDateTimeFormat());
        $this->assertNull($connection->getTimezone());
    }

    public function testSerialization(): void
    {
        $connection = new Connection(
            'sqlite::memory:',
            dateTimeFormat: 'Y-m-d H:i:s.u',
            timezone: new DateTimeZone('America/New_York'),
        );

        /** @var Connection $connection2 */
        $connection2 = unserialize(serialize($connection));

        $this->assertSame('Y-m-d H:i:s.u', $connection2->getDateTimeFormat());
        $this->assertSame('America/New_York', $connection2->getTimezone()->getName());
    }

    public function testSerializationWithoutTimezone(): void
    {
        /** @var Connection $connection */
        $connection = unserialize(serialize(new Connection('sqlite::memory:')));

        $this->assertSame('Y-m-d H:i:s', $connection->getDateTimeFormat());
        $this->assertNull($connection->getTimezone());
    }

    public function testUnserializeLegacyPayload(): void
    {
        $connection = new Connection(
            'sqlite::memory:',
            dateTimeFormat: 'Y-m-d',
            timezone: new DateTimeZone('UTC'),
        );
        $connection->__unserialize([
            'dsn' => 'sqlite::memory:',
            'username' => null,
            'password' => null,
            'readDsn' => null,
            'name' => Connection::DEFAULT_NAME,
            'logger' => null,
        ]);

        $this->assertSame('Y-m-d H:i:s', $connection->getDateTimeFormat());
        $this->assertNull($connection->getTimezone());
    }
}
