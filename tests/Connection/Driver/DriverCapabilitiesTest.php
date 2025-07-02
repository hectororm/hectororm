<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Connection\Tests\Driver;

use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MariaDBCapabilities;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Connection\Driver\PostgreSQLCapabilities;
use Hector\Connection\Driver\SQLiteCapabilities;
use Hector\Connection\Driver\UnknownCapabilities;
use PHPUnit\Framework\TestCase;

class DriverCapabilitiesTest extends TestCase
{
    public function providerCapabilities(): array
    {
        return [
            ['mysql', '8.0.0', MySQLCapabilities::class, true, true, true, true, true],
            ['mysql', '5.7.0', MySQLCapabilities::class, true, false, false, true, true],
            ['mariadb', '10.6.0', MariaDBCapabilities::class, true, true, true, true, true],
            ['mariadb', '10.2.0', MariaDBCapabilities::class, true, false, true, true, true],
            ['pgsql', '13.3', PostgreSQLCapabilities::class, true, true, true, true, true],
            ['sqlite', '3.35', SQLiteCapabilities::class, false, false, true, true, false],
            ['vitess', '8.0', UnknownCapabilities::class, false, false, false, false, false],
            ['unknown', '1.0', UnknownCapabilities::class, false, false, false, false, false],
        ];
    }

    /**
     * @dataProvider providerCapabilities
     */
    public function testCapabilities(
        string $driver,
        string $version,
        string $expectedClass,
        bool $lock,
        bool $lockAndSkip,
        bool $windowFunctions,
        bool $jsonSupport,
        bool $strictMode
    ): void {
        $driverInfoMock = $this->createMock(DriverInfo::class);
        $driverInfoMock->method('getDriver')->willReturn($driver);
        $driverInfoMock->method('getVersion')->willReturn($version);

        $capabilities = match ($driver) {
            'mysql' => new MySQLCapabilities($driverInfoMock),
            'mariadb' => new MariaDBCapabilities($driverInfoMock),
            'pgsql' => new PostgreSQLCapabilities($driverInfoMock),
            'sqlite' => new SQLiteCapabilities($driverInfoMock),
            default => new UnknownCapabilities(),
        };

        $this->assertInstanceOf($expectedClass, $capabilities);
        $this->assertSame($lock, $capabilities->hasLock());
        $this->assertSame($lockAndSkip, $capabilities->hasLockAndSkip());
        $this->assertSame($windowFunctions, $capabilities->hasWindowFunctions());
        $this->assertSame($jsonSupport, $capabilities->hasJson());
        $this->assertSame($strictMode, $capabilities->hasStrictMode());
    }
}
