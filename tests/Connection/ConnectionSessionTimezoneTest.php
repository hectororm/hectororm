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

use DateTimeImmutable;
use DateTimeZone;
use Hector\Connection\Connection;
use Hector\Connection\Exception\ConnectionException;
use Hector\Connection\Log\LogEntry;
use Hector\Connection\Log\Logger;
use PDO;
use PDOException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ConnectionSessionTimezoneTest extends TestCase
{
    private const TABLE = 'hector_session_timezone_test';

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        if (null !== $this->connection) {
            $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        }
        $this->connection = null;
    }

    private function getMysqlDsn(): string
    {
        $dsn = getenv('MYSQL_DSN');
        if (false === $dsn || '' === $dsn) {
            $this->markTestSkipped('MYSQL_DSN is required');
        }

        return $dsn;
    }

    private function hasTimezoneTables(string $dsn): bool
    {
        return (new PDO($dsn))->query('SELECT COUNT(*) FROM mysql.time_zone_name')->fetchColumn() > 0;
    }

    private function sessionTimezone(PDO $pdo): string
    {
        return $pdo->query('SELECT @@session.time_zone')->fetchColumn();
    }

    /**
     * @return PDO&MockObject
     */
    private function mockPdo(string $driver, string $version = '1.0.0'): MockObject
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, $driver],
            [PDO::ATTR_SERVER_VERSION, $version],
        ]);
        $pdo->method('quote')->willReturnCallback(static fn(string $value): string => "'" . $value . "'");

        return $pdo;
    }

    public static function statementProvider(): array
    {
        return [
            'mysql utc' => ['mysql', '8.0.31', 'UTC', "SET time_zone = '+00:00'"],
            'mysql named' => ['mysql', '8.0.31', 'Europe/Paris', "SET time_zone = 'Europe/Paris'"],
            'mysql positive offset' => ['mysql', '8.0.31', '+05:30', "SET time_zone = '+05:30'"],
            'mysql negative offset' => ['mysql', '8.0.31', '-04:00', "SET time_zone = '-04:00'"],
            'mysql abbreviation' => ['mysql', '8.0.31', 'EST', "SET time_zone = '-05:00'"],
            'mariadb utc' => ['mysql', '10.11.2-MariaDB', 'UTC', "SET time_zone = '+00:00'"],
            'mariadb named' => ['mysql', '10.11.2-MariaDB', 'Europe/Paris', "SET time_zone = 'Europe/Paris'"],
            'vitess utc' => ['mysql', '8.0.30-Vitess', 'UTC', "SET time_zone = '+00:00'"],
            'pgsql utc' => ['pgsql', '17.0', 'UTC', "SET TIME ZONE 'UTC'"],
            'pgsql named' => ['pgsql', '17.0', 'Europe/Paris', "SET TIME ZONE 'Europe/Paris'"],
            'pgsql positive offset' => [
                'pgsql',
                '17.0',
                '+05:30',
                "SET TIME ZONE INTERVAL '+05:30' HOUR TO MINUTE",
            ],
            'pgsql negative offset' => [
                'pgsql',
                '17.0',
                '-04:00',
                "SET TIME ZONE INTERVAL '-04:00' HOUR TO MINUTE",
            ],
        ];
    }

    /**
     * @dataProvider statementProvider
     */
    public function testSessionTimezoneStatement(
        string $driver,
        string $version,
        string $timezone,
        string $expected
    ): void {
        $pdo = $this->mockPdo($driver, $version);
        $pdo->expects($this->once())->method('exec')->with($expected)->willReturn(0);

        Connection::fromPdo($pdo, timezone: new DateTimeZone($timezone));
    }

    public static function driverWithoutSessionTimezoneProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'unknown' => ['sqlsrv'],
        ];
    }

    /**
     * @dataProvider driverWithoutSessionTimezoneProvider
     */
    public function testDriverWithoutSessionTimezone(string $driver): void
    {
        $pdo = $this->mockPdo($driver);
        $pdo->expects($this->never())->method('exec');

        Connection::fromPdo($pdo, timezone: new DateTimeZone('UTC'));
    }

    public function testSessionUntouchedWithoutTimezone(): void
    {
        $pdo = $this->mockPdo('mysql', '8.0.31');
        $readPdo = $this->mockPdo('mysql', '8.0.31');
        $pdo->expects($this->never())->method('exec');
        $readPdo->expects($this->never())->method('exec');

        Connection::fromPdo($pdo, $readPdo);
    }

    public function testFromPdoSetsReadPdoSession(): void
    {
        $pdo = $this->mockPdo('mysql', '8.0.31');
        $readPdo = $this->mockPdo('mysql', '8.0.31');
        $pdo->expects($this->once())->method('exec')->with("SET time_zone = '+00:00'")->willReturn(0);
        $readPdo->expects($this->once())->method('exec')->with("SET time_zone = '+00:00'")->willReturn(0);

        $connection = Connection::fromPdo($pdo, $readPdo, timezone: new DateTimeZone('UTC'));

        $this->assertSame($pdo, $connection->getPdo());
        $this->assertSame($readPdo, $connection->getReadPdo());
    }

    public function testFailureThrowsConnectionException(): void
    {
        $failure = new PDOException("SQLSTATE[HY000]: General error: 1298 Unknown or incorrect time zone");
        $pdo = $this->mockPdo('mysql', '8.0.31');
        $pdo->method('exec')->willThrowException($failure);

        try {
            Connection::fromPdo($pdo, name: 'foo', timezone: new DateTimeZone('Europe/Paris'));
            $this->fail('Session timezone must fail');
        } catch (ConnectionException $exception) {
            $this->assertSame(
                'Unable to set the session timezone of "foo" to "Europe/Paris": ' . $failure->getMessage(),
                $exception->getMessage()
            );
            $this->assertSame($failure, $exception->getPrevious());
        }
    }

    public function testFailureWithSilentErrorMode(): void
    {
        $pdo = $this->mockPdo('mysql', '8.0.31');
        $pdo->method('exec')->willReturn(false);
        $pdo->method('errorInfo')->willReturn(['HY000', 1298, 'Unknown or incorrect time zone']);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage(
            'Unable to set the session timezone of "default" to "Europe/Paris": Unknown or incorrect time zone'
        );

        Connection::fromPdo($pdo, timezone: new DateTimeZone('Europe/Paris'));
    }

    public function testStatementIsLogged(): void
    {
        $logger = new Logger();
        $pdo = $this->mockPdo('pgsql', '17.0');
        $pdo->method('exec')->willReturn(0);

        Connection::fromPdo($pdo, logger: $logger, timezone: new DateTimeZone('Europe/Paris'));

        $logs = $logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertSame("SET TIME ZONE 'Europe/Paris'", $logs[0]->getStatement());
        $this->assertSame(LogEntry::TYPE_QUERY, $logs[0]->getType());
    }

    public function testSqlite(): void
    {
        $logger = new Logger();
        $connection = new Connection(
            'sqlite::memory:',
            readDsn: 'sqlite::memory:',
            logger: $logger,
            timezone: new DateTimeZone('Europe/Paris'),
        );

        $this->assertSame('1', (string)$connection->getPdo()->query('SELECT 1')->fetchColumn());
        $this->assertSame('1', (string)$connection->getReadPdo()->query('SELECT 1')->fetchColumn());
        $this->assertCount(2, $logger->getLogs());
    }

    public function testMysqlSessionUntouchedWithoutTimezone(): void
    {
        $connection = new Connection($this->getMysqlDsn());

        $this->assertSame(
            $connection->getPdo()->query('SELECT @@global.time_zone')->fetchColumn(),
            $this->sessionTimezone($connection->getPdo())
        );
    }

    public static function mysqlTimezoneProvider(): array
    {
        return [
            'utc' => ['UTC', '+00:00'],
            'positive offset' => ['+05:30', '+05:30'],
            'negative offset' => ['-04:00', '-04:00'],
            'abbreviation' => ['EST', '-05:00'],
        ];
    }

    /**
     * @dataProvider mysqlTimezoneProvider
     */
    public function testMysqlSession(string $timezone, string $expected): void
    {
        $connection = new Connection($this->getMysqlDsn(), timezone: new DateTimeZone($timezone));

        $this->assertSame($expected, $this->sessionTimezone($connection->getPdo()));
    }

    public function testMysqlReadSession(): void
    {
        $dsn = $this->getMysqlDsn();
        $connection = new Connection($dsn, readDsn: $dsn, timezone: new DateTimeZone('-04:00'));

        $this->assertNotSame($connection->getPdo(), $connection->getReadPdo());
        $this->assertSame('-04:00', $this->sessionTimezone($connection->getPdo()));
        $this->assertSame('-04:00', $this->sessionTimezone($connection->getReadPdo()));
    }

    public function testMysqlFromPdo(): void
    {
        $dsn = $this->getMysqlDsn();
        $connection = Connection::fromPdo(new PDO($dsn), new PDO($dsn), timezone: new DateTimeZone('+05:30'));

        $this->assertSame('+05:30', $this->sessionTimezone($connection->getPdo()));
        $this->assertSame('+05:30', $this->sessionTimezone($connection->getReadPdo()));
    }

    public function testMysqlSessionAfterUnserialize(): void
    {
        $connection = new Connection($this->getMysqlDsn(), timezone: new DateTimeZone('+05:30'));

        /** @var Connection $connection2 */
        $connection2 = unserialize(serialize($connection));

        $this->assertSame('+05:30', $this->sessionTimezone($connection2->getPdo()));
    }

    public function testMysqlNamedTimezone(): void
    {
        $dsn = $this->getMysqlDsn();
        if (false === $this->hasTimezoneTables($dsn)) {
            $this->markTestSkipped('Timezone tables are required');
        }

        $connection = new Connection($dsn, timezone: new DateTimeZone('Europe/Paris'));

        $this->assertSame('Europe/Paris', $this->sessionTimezone($connection->getPdo()));
    }

    public function testMysqlNamedTimezoneWithoutTimezoneTables(): void
    {
        $dsn = $this->getMysqlDsn();
        if (true === $this->hasTimezoneTables($dsn)) {
            $this->markTestSkipped('Timezone tables must not be loaded');
        }

        $connection = new Connection($dsn, timezone: new DateTimeZone('Europe/Paris'));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Unable to set the session timezone of "default" to "Europe/Paris"');

        $connection->getPdo();
    }

    public function testMysqlFailureDoesNotKeepPdo(): void
    {
        $dsn = $this->getMysqlDsn();
        // Out of the range accepted by MySQL and MariaDB.
        $connection = new Connection($dsn, readDsn: $dsn, timezone: new DateTimeZone('+15:00'));

        foreach (['pdo' => 'getPdo', 'readPdo' => 'getReadPdo'] as $property => $method) {
            try {
                $connection->$method();
                $this->fail('Session timezone must fail');
            } catch (ConnectionException $exception) {
                $this->assertStringStartsWith(
                    'Unable to set the session timezone of "default" to "+15:00": ',
                    $exception->getMessage()
                );
            }

            $reflectionProperty = new ReflectionProperty(Connection::class, $property);
            PHP_VERSION_ID < 80100 && $reflectionProperty->setAccessible(true);
            $this->assertNull($reflectionProperty->getValue($connection));
        }
    }

    public function testMysqlServerDatesAreAlignedWithBoundDates(): void
    {
        $this->connection = new Connection($this->getMysqlDsn(), timezone: new DateTimeZone('+05:45'));
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY, created_at DATETIME, bound_at DATETIME)'
        );

        $this->connection->execute(
            'INSERT INTO ' . self::TABLE . ' (id, created_at, bound_at) VALUES (1, NOW(), ?)',
            [new DateTimeImmutable('now', new DateTimeZone('America/New_York'))]
        );

        $row = $this->connection->fetchOne('SELECT created_at, bound_at FROM ' . self::TABLE);
        $utc = new DateTimeZone('UTC');
        $createdAt = (new DateTimeImmutable($row['created_at'], $utc))->getTimestamp();
        $boundAt = (new DateTimeImmutable($row['bound_at'], $utc))->getTimestamp();

        // Both values are in the storage timezone: only the execution time separates them.
        $this->assertLessThan(300, abs($createdAt - $boundAt));
    }
}
