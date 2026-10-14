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

class CheckConstraintCapabilitiesTest extends TestCase
{
    public static function providerCheckConstraints(): array
    {
        return [
            'MySQL 5.7 ignores CHECK' => ['mysql', '5.7.44', false, false],
            'MySQL before enforcement' => ['mysql', '8.0.15', false, false],
            'MySQL first enforced version' => ['mysql', '8.0.16', true, true],
            'MySQL current LTS' => ['mysql', '8.4.0', true, true],
            'MySQL version suffix' => ['mysql', '8.0.16-log', true, true],
            'MySQL old version suffix' => ['mysql', '8.0.15-log', false, false],
            'MySQL unknown version' => ['mysql', 'unknown', false, false],
            'MySQL incomplete version' => ['mysql', '8.0', false, false],
            'MariaDB before enforcement' => ['mariadb', '10.2.0', false, false],
            'MariaDB first enforced version' => ['mariadb', '10.2.1', true, false],
            'MariaDB current LTS' => ['mariadb', '11.4.0', true, false],
            'MariaDB version suffix' => ['mariadb', '10.2.1-MariaDB', true, false],
            'MariaDB unknown version' => ['mariadb', 'unknown', false, false],
            'MariaDB incomplete version' => ['mariadb', '10.2', false, false],
            'SQLite rebuild required' => ['sqlite', '3.35.0', true, false],
            'PostgreSQL native enforcement' => ['pgsql', '13.3', true, false],
        ];
    }

    /**
     * @dataProvider providerCheckConstraints
     */
    public function testCheckConstraints(
        string $driver,
        string $version,
        bool $enforced,
        bool $dropCheck,
    ): void {
        $capabilities = (new DriverInfo($driver, $version))->getCapabilities();

        self::assertInstanceOf(DriverCapabilities::class, $capabilities);
        self::assertSame($enforced, $capabilities->hasCheckConstraints());
        self::assertSame($dropCheck, $capabilities->hasDropCheck());
    }

    public static function providerUnsupportedDrivers(): array
    {
        return [
            'Vitess compatible version' => ['vitess', '8.0.30'],
            'Vitess newer version' => ['vitess', '22.0.0'],
            'unknown engine' => ['unknown', '99.0.0'],
        ];
    }

    /**
     * @dataProvider providerUnsupportedDrivers
     */
    public function testUnsupportedDriversDoNotAdvertiseCheckSupport(string $driver, string $version): void
    {
        $capabilities = (new DriverInfo($driver, $version))->getCapabilities();

        self::assertInstanceOf(DriverCapabilities::class, $capabilities);
        self::assertFalse($capabilities->hasCheckConstraints());
        self::assertFalse($capabilities->hasDropCheck());
    }

    public function testManuallyConstructedCapabilitiesDoNotAssumeEngineCompatibility(): void
    {
        $driverInfo = new DriverInfo('vitess', '22.0.0');

        foreach ([
            new MySQLCapabilities($driverInfo),
            new MariaDBCapabilities($driverInfo),
            new SQLiteCapabilities($driverInfo),
            new PostgreSQLCapabilities($driverInfo),
        ] as $capabilities) {
            self::assertFalse($capabilities->hasCheckConstraints());
            self::assertFalse($capabilities->hasDropCheck());
        }
    }

    public static function providerPdoVersions(): array
    {
        return [
            'MySQL with suffix' => ['8.0.16-log', true],
            'MariaDB compatibility prefix' => ['5.5.5-10.2.1-MariaDB', true],
            'MariaDB before enforcement' => ['5.5.5-10.2.0-MariaDB', false],
            'Vitess on mysql PDO driver' => ['8.0.30-vitess-22.0.0', false],
        ];
    }

    /**
     * @dataProvider providerPdoVersions
     */
    public function testPdoDriverResolution(string $version, bool $enforced): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, 'mysql'],
            [PDO::ATTR_SERVER_VERSION, $version],
        ]);

        $capabilities = DriverInfo::fromPDO($pdo)->getCapabilities();

        self::assertSame($enforced, $capabilities->hasCheckConstraints());
    }
}
