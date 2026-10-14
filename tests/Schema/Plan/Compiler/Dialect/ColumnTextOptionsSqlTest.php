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
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Schema\Column;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class ColumnTextOptionsSqlTest extends TestCase
{
    /** @dataProvider legacyDefinitions */
    public function testLegacyRenamePreservesTextOptions(bool $mariaDb, bool $generated): void
    {
        $column = new Column('reference', 0, null, false, 'char', maxlength: 36,
            charset: 'ascii', collation: 'ascii_bin', generation_expression: $generated ? "'abc'" : null);
        $table = new Table('main', Table::TYPE_TABLE, 'items', columns: ['reference' => $column]);
        $schema = new Schema('default', 'main', 'utf8mb4', tables: ['items' => $table]);
        $compiler = new MySQLCompiler($mariaDb
            ? new MariaDBCapabilities(new DriverInfo('mariadb', '10.5.1'))
            : new MySQLCapabilities(new DriverInfo('mysql', '5.7.44')));
        $plan = new Plan();
        $plan->alter('items')->renameColumn('reference', 'code');

        $suffix = $generated ? " GENERATED ALWAYS AS ('abc') VIRTUAL" : '';
        if (false === $generated || false === $mariaDb) {
            $suffix .= ' NOT NULL';
        }
        $this->assertSame([
            'ALTER TABLE `items` CHANGE COLUMN `reference` `code` char(36) CHARACTER SET `ascii` COLLATE `ascii_bin`'
            . $suffix,
        ], [...$plan->getStatements($compiler, $schema)]);
    }

    public static function legacyDefinitions(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

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
