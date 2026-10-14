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

namespace Hector\Schema\Tests\Plan\Compiler\Dialect;

use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MariaDBCapabilities;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use PHPUnit\Framework\TestCase;

class ColumnTextOptionsSqlTest extends TestCase
{
    /** @dataProvider mysqlOptions */
    public function testMysqlCreateAddAndModify(?string $charset, ?string $collation, string $options): void
    {
        $plan = new Plan();
        $plan->create('items', charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci')
            ->addColumn('reference', 'CHAR(36)', charset: $charset, collation: $collation);
        $plan->alter('items')->addColumn('alias', 'VARCHAR(64)', nullable: true, after: 'reference',
            charset: $charset, collation: $collation);
        $plan->alter('items')->modifyColumn('reference', 'VARCHAR(64)', first: true,
            charset: $charset, collation: $collation);
        $plan->alter('items')->modifyColumn('reference', 'VARCHAR(128)');

        $this->assertSame([
            "CREATE TABLE `items` (\n  `reference` CHAR(36)$options NOT NULL\n) "
            . 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            "ALTER TABLE `items` ADD COLUMN `alias` VARCHAR(64)$options NULL DEFAULT NULL AFTER `reference`",
            "ALTER TABLE `items` MODIFY COLUMN `reference` VARCHAR(64)$options NOT NULL FIRST",
            'ALTER TABLE `items` MODIFY COLUMN `reference` VARCHAR(128) NOT NULL',
        ], [...$plan->getStatements(new MySQLCompiler())]);
    }

    public static function mysqlOptions(): array
    {
        return [
            'defaults' => [null, null, ''],
            'charset only' => ['ascii', null, ' CHARACTER SET `ascii`'],
            'collation only' => [null, 'ascii_bin', ' COLLATE `ascii_bin`'],
            'both' => ['utf8mb4', 'utf8mb4_0900_ai_ci', ' CHARACTER SET `utf8mb4` COLLATE `utf8mb4_0900_ai_ci`'],
            'quoted native names' => ['a`b', 'c`d', ' CHARACTER SET `a``b` COLLATE `c``d`'],
        ];
    }

    /** @dataProvider sqliteOptions */
    public function testSqliteCreateAndAdd(?string $charset, ?string $collation, string $options): void
    {
        $plan = new Plan();
        $plan->create('items', charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci')
            ->addColumn('reference', 'CHAR(36)', charset: $charset, collation: $collation);
        $plan->alter('items')->addColumn('alias', 'VARCHAR(64)', nullable: true,
            charset: $charset, collation: $collation);

        $this->assertSame([
            "CREATE TABLE \"items\" (\n  \"reference\" CHAR(36)$options NOT NULL\n)",
            "ALTER TABLE \"items\" ADD COLUMN \"alias\" VARCHAR(64)$options DEFAULT NULL",
        ], [...$plan->getStatements(new SqliteCompiler())]);
    }

    public static function sqliteOptions(): array
    {
        return [
            'defaults' => [null, null, ''],
            'charset ignored' => ['ascii', null, ''],
            'collation only' => [null, 'NOCASE', ' COLLATE "NOCASE"'],
            'both' => ['ascii', 'BINARY', ' COLLATE "BINARY"'],
            'no translation' => ['ascii', 'ascii_bin', ' COLLATE "ascii_bin"'],
            'quoted custom name' => [null, 'custom"collation', ' COLLATE "custom""collation"'],
        ];
    }

    /** @dataProvider generatedOptions */
    public function testGeneratedColumns(string $driver, bool $stored): void
    {
        $sqlite = 'sqlite' === $driver;
        $compiler = match ($driver) {
            'sqlite' => new SqliteCompiler(),
            'mariadb' => new MySQLCompiler(new MariaDBCapabilities(new DriverInfo('mariadb', '10.5.29'))),
            default => new MySQLCompiler(),
        };
        $collation = $sqlite ? 'BINARY' : 'ascii_bin';
        $plan = new Plan();
        $plan->create('items')->addColumn('reference', 'CHAR(36)', generated: new Generated("'abc'", stored: $stored),
            charset: 'ascii', collation: $collation);

        $quote = $sqlite ? '"' : '`';
        $options = $sqlite ? ' COLLATE "BINARY"' : ' CHARACTER SET `ascii` COLLATE `ascii_bin`';
        $mode = $stored ? 'STORED' : 'VIRTUAL';
        $nullable = 'mariadb' === $driver ? '' : ' NOT NULL';
        $this->assertSame([
            "CREATE TABLE {$quote}items{$quote} (\n  {$quote}reference{$quote} CHAR(36)$options "
            . "GENERATED ALWAYS AS ('abc') $mode$nullable\n)",
        ], [...$plan->getStatements($compiler)]);
    }

    public static function generatedOptions(): iterable
    {
        foreach (['mysql', 'mariadb', 'sqlite'] as $driver) {
            foreach ([false, true] as $stored) {
                yield [$driver, $stored];
            }
        }
    }
}
