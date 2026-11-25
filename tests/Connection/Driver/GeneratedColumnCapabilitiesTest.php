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

namespace Hector\Connection\Tests\Driver;

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MariaDBCapabilities;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Connection\Driver\PostgreSQLCapabilities;
use Hector\Connection\Driver\SQLiteCapabilities;
use PDO;
use PHPUnit\Framework\TestCase;

class GeneratedColumnCapabilitiesTest extends TestCase
{
    public static function versions(): iterable
    {
        foreach ([
            ['mysql', '5.7.5', false, false],
            ['mysql', '5.7.6', true, true],
            ['mysql', '8.4.0', true, true],
            ['mariadb', '10.2.0', false, false],
            ['mariadb', '10.2.1', true, true],
            ['mariadb', '11.4.0', true, true],
            ['sqlite', '3.30.1', false, false],
            ['sqlite', '3.31.0', true, true],
            ['sqlite', '3.45.0', true, true],
            ['pgsql', '11.22', false, false],
            ['pgsql', '12.0', true, false],
            ['pgsql', '17.9', true, false],
            ['pgsql', '18.0', true, true],
            ['pgsql', '18.0.0', true, true],
            ['pgsql', '19.0', true, true],
            ['unknown', '99.0.0', false, false],
            ['vitess', '22.0.0', false, false],
        ] as [$driver, $version, $stored, $virtual]) {
            foreach (['', '-log', '+build'] as $suffix) {
                yield $driver . ' ' . $version . $suffix => [$driver, $version . $suffix, $stored, $virtual];
            }
        }

        yield 'PostgreSQL distribution suffix' => ['pgsql', '18.0 (Debian 18.0-1)', true, true];
        yield 'PostgreSQL old distribution suffix' => ['pgsql', '11.22 (Debian 11.22-1)', false, false];

        foreach (['mysql', 'mariadb', 'sqlite', 'pgsql'] as $driver) {
            foreach (['', 'unknown', '99', '99.x.0', '99.0.0rc1'] as $version) {
                yield $driver . ' invalid ' . $version => [$driver, $version, false, false];
            }
        }

        foreach (['mysql', 'mariadb', 'sqlite'] as $driver) {
            yield $driver . ' incomplete' => [$driver, '99.0', false, false];
        }
    }

    /**
     * @dataProvider versions
     */
    public function testGeneratedColumns(string $driver, string $version, bool $stored, bool $virtual): void
    {
        $capabilities = (new DriverInfo($driver, $version))->getCapabilities();

        self::assertInstanceOf(DriverCapabilities::class, $capabilities);
        self::assertSame($stored, $capabilities->hasGeneratedColumns());
        self::assertSame($virtual, $capabilities->hasVirtualGeneratedColumns());
    }

    public function testManuallyConstructedCapabilitiesRejectOtherEngines(): void
    {
        $driverInfo = new DriverInfo('vitess', '99.0.0');

        foreach ([
            new MySQLCapabilities($driverInfo),
            new MariaDBCapabilities($driverInfo),
            new PostgreSQLCapabilities($driverInfo),
            new SQLiteCapabilities($driverInfo),
        ] as $capabilities) {
            self::assertFalse($capabilities->hasGeneratedColumns());
            self::assertFalse($capabilities->hasVirtualGeneratedColumns());
        }
    }

    public static function pdoVersions(): array
    {
        return [
            ['mysql', '5.7.6-log', true, true],
            ['mysql', '5.7.5-log', false, false],
            ['mysql', '5.5.5-10.2.1-MariaDB', true, true],
            ['mysql', '5.5.5-10.2.0-MariaDB', false, false],
            ['mysql', '8.0.30-vitess-22.0.0', false, false],
            ['pgsql', '12.0 (Debian 12.0-1)', true, false],
            ['pgsql', '18.0 (Debian 18.0-1)', true, true],
        ];
    }

    /**
     * @dataProvider pdoVersions
     */
    public function testPdoResolution(string $driver, string $version, bool $stored, bool $virtual): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, $driver],
            [PDO::ATTR_SERVER_VERSION, $version],
        ]);

        $capabilities = DriverInfo::fromPDO($pdo)->getCapabilities();

        self::assertSame($stored, $capabilities->hasGeneratedColumns());
        self::assertSame($virtual, $capabilities->hasVirtualGeneratedColumns());
    }
}
