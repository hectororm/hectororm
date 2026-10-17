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
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

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

    public function providerIdentifierQuote(): array
    {
        return [
            ['mysql', '8.0.0', '`'],
            ['mysql', '5.7.0', '`'],
            ['mariadb', '10.6.0', '`'],
            ['pgsql', '13.3', '"'],
            ['sqlite', '3.35', '"'],
            ['vitess', '8.0', '`'],
            ['unknown', '1.0', '`'],
        ];
    }

    /**
     * @dataProvider providerIdentifierQuote
     */
    public function testGetIdentifierQuote(
        string $driver,
        string $version,
        string $expectedQuote,
    ): void {
        $driverInfo = new DriverInfo($driver, $version);

        $this->assertSame($expectedQuote, $driverInfo->getIdentifierQuote());
    }

    private static function pdoException(string $sqlState, int $driverCode): PDOException
    {
        $exception = new PDOException(sprintf('SQLSTATE[%s]: %d', $sqlState, $driverCode));
        $exception->errorInfo = [$sqlState, $driverCode, 'Driver message'];

        return $exception;
    }

    public function providerRetryable(): array
    {
        return [
            'mysql deadlock' => ['mysql', self::pdoException('40001', 1213), true],
            'mysql lock wait timeout' => ['mysql', self::pdoException('HY000', 1205), true],
            'mysql duplicate key' => ['mysql', self::pdoException('23000', 1062), false],
            'mariadb deadlock' => ['mariadb', self::pdoException('40001', 1213), true],
            'mariadb lock wait timeout' => ['mariadb', self::pdoException('HY000', 1205), true],
            'vitess deadlock' => ['vitess', self::pdoException('40001', 1213), true],
            'pgsql serialization failure' => ['pgsql', self::pdoException('40001', 7), true],
            'pgsql deadlock' => ['pgsql', self::pdoException('40P01', 7), true],
            'pgsql unique violation' => ['pgsql', self::pdoException('23505', 7), false],
            'sqlite busy' => ['sqlite', self::pdoException('HY000', 5), true],
            'sqlite locked' => ['sqlite', self::pdoException('HY000', 6), true],
            'sqlite busy snapshot (extended)' => ['sqlite', self::pdoException('HY000', 517), true],
            'sqlite constraint' => ['sqlite', self::pdoException('23000', 19), false],
            'unknown serialization failure' => ['oci', self::pdoException('40001', 8177), true],
            'unknown other' => ['oci', self::pdoException('HY000', 1205), false],
            'wrapped' => ['mysql', new RuntimeException('Wrapped', 0, self::pdoException('40001', 1213)), true],
            'not a pdo exception' => ['mysql', new RuntimeException('Deadlock', 1213), false],
            'pdo exception without error info' => ['mysql', new PDOException('Deadlock'), false],
        ];
    }

    /**
     * @dataProvider providerRetryable
     */
    public function testIsRetryable(string $driver, Throwable $exception, bool $expected): void
    {
        $driverInfo = new DriverInfo($driver, '1.0.0');

        $this->assertSame($expected, $driverInfo->isRetryable($exception));
    }
}
