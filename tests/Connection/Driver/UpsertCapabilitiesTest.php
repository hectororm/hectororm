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

use Hector\Connection\Driver\DriverInfo;
use PHPUnit\Framework\TestCase;

class UpsertCapabilitiesTest extends TestCase
{
    public static function providerUpsert(): array
    {
        return [
            'MySQL 5.7' => ['mysql', '5.7.44', true, false],
            'MySQL before row alias' => ['mysql', '8.0.18', true, false],
            'MySQL first row alias version' => ['mysql', '8.0.19', true, true],
            'MySQL current LTS' => ['mysql', '8.4.0', true, true],
            'MySQL version suffix' => ['mysql', '8.0.19-log', true, true],
            'MySQL old version suffix' => ['mysql', '8.0.18-log', true, false],
            'MySQL unknown version' => ['mysql', 'unknown', true, false],
            'MySQL incomplete version' => ['mysql', '8.4', true, false],
            'MariaDB oldest tested' => ['mariadb', '10.5.0', true, false],
            'MariaDB current LTS' => ['mariadb', '11.8.0', true, false],
            'SQLite before upsert' => ['sqlite', '3.23.1', false, false],
            'SQLite first upsert version' => ['sqlite', '3.24.0', true, false],
            'SQLite recent' => ['sqlite', '3.46.1', true, false],
            'PostgreSQL before upsert' => ['pgsql', '9.4.26', false, false],
            'PostgreSQL first upsert version' => ['pgsql', '9.5.0', true, false],
            'PostgreSQL recent' => ['pgsql', '17.2', true, false],
            'PostgreSQL distribution suffix' => ['pgsql', '17.2 (debian 17.2-1.pgdg120+1)', true, false],
            'Vitess' => ['vitess', '8.0.30', false, false],
            'unknown engine' => ['unknown', '99.0.0', false, false],
        ];
    }

    /**
     * @dataProvider providerUpsert
     */
    public function testUpsert(string $driver, string $version, bool $upsert, bool $insertRowAlias): void
    {
        $capabilities = (new DriverInfo($driver, $version))->getCapabilities();

        self::assertSame($upsert, $capabilities->hasUpsert());
        self::assertSame($insertRowAlias, $capabilities->hasInsertRowAlias());
    }
}
