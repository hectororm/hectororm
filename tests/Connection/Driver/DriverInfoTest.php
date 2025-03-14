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

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Connection\Driver\DriverInfo;
use PDO;
use PHPUnit\Framework\TestCase;

class DriverInfoTest extends TestCase
{
    public function providerPDOData(): array
    {
        return [
            ['mysql', '8.0.31', 'mysql', '8.0.31'],
            ['mysql', '5.7.35', 'mysql', '5.7.35'],
            ['mysql', '5.5.5-10.5.13-MariaDB', 'mariadb', '10.5.13'],
            ['mysql', '10.6.5-MariaDB', 'mariadb', '10.6.5'],
            ['pgsql', '13.3', 'pgsql', '13.3'],
            ['sqlite', '3.34.1', 'sqlite', '3.34.1'],
            ['vitess', '5.5.5-8.0.0-vitess', 'vitess', '8.0.0'],
        ];
    }

    /**
     * @dataProvider providerPDOData
     */
    public function testFromPDO(
        string $pdoDriver,
        string $pdoVersion,
        string $expectedDriver,
        string $expectedVersion
    ): void {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, $pdoDriver],
            [PDO::ATTR_SERVER_VERSION, $pdoVersion],
        ]);

        $driverInfo = DriverInfo::fromPDO($pdoMock);

        $this->assertSame($expectedDriver, $driverInfo->getDriver());
        $this->assertSame($expectedVersion, $driverInfo->getVersion());
    }

    public function testGetCapabilities(): void
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('getAttribute')->willReturnMap([
            [PDO::ATTR_DRIVER_NAME, 'mysql'],
            [PDO::ATTR_SERVER_VERSION, '8.0.31'],
        ]);

        $driverInfo = DriverInfo::fromPDO($pdoMock);
        $capabilities = $driverInfo->getCapabilities();

        $this->assertInstanceOf(DriverCapabilities::class, $capabilities);
    }
}
