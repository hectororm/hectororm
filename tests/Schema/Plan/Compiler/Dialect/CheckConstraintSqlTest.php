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

use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\UnknownCapabilities;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Compiler\Compiler;
use Hector\Schema\Plan\Compiler\Dialect\MySQLDialect;
use Hector\Schema\Plan\Compiler\Dialect\SqliteDialect;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Operation\AddCheck;
use Hector\Schema\Plan\Operation\DropCheck;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class CheckConstraintSqlTest extends TestCase
{
    public static function providerDrivers(): array
    {
        return [['mysql', '8.0.16'], ['mariadb', '10.2.1'], ['sqlite', '3.35.0']];
    }

    /**
     * @dataProvider providerDrivers
     */
    public function testCreateQuotesIdentifiersAndKeepsTheExpressionVerbatim(string $driver, string $version): void
    {
        $capabilities = (new DriverInfo($driver, $version))->getCapabilities();
        $dialect = 'sqlite' === $driver ? new SqliteDialect($capabilities) : new MySQLDialect($capabilities);
        $quote = 'sqlite' === $driver ? '"' : '`';
        $plan = new Plan();
        $expression = " price >= 0 AND label <> 'a,b (c)' /* raw SQL */\n";
        $plan->create('prod' . $quote . 'ucts')
            ->addCheck('chk' . $quote . 'price', new Raw($expression))
            ->addColumn('price', 'INTEGER')
            ->addColumn('label', 'TEXT');

        self::assertSame([
            "CREATE TABLE {$quote}prod{$quote}{$quote}ucts{$quote} (\n" .
            "  {$quote}price{$quote} INTEGER NOT NULL,\n" .
            "  {$quote}label{$quote} TEXT NOT NULL,\n" .
            "  CONSTRAINT {$quote}chk{$quote}{$quote}price{$quote} CHECK ($expression)\n)",
        ], [...(new Compiler($dialect))->compile($plan)]);
    }

    public static function providerMySqlFamilies(): array
    {
        return [['mysql', '8.0.16', 'CHECK'], ['mariadb', '10.2.1', 'CONSTRAINT']];
    }

    /**
     * @dataProvider providerMySqlFamilies
     */
    public function testAlterKeepsCheckClausesInOrder(string $driver, string $version, string $dropKeyword): void
    {
        $plan = new Plan();
        $plan->alter('prod`ucts')
            ->dropCheck('chk`price')
            ->addColumn('ceiling', 'INT', nullable: true)
            ->addCheck('chk`price', 'price <= ceiling')
            ->renameTable('renamed');

        self::assertSame([
            'ALTER TABLE `prod``ucts` DROP ' . $dropKeyword . ' `chk``price`, ' .
            'ADD COLUMN `ceiling` INT NULL DEFAULT NULL, ADD CONSTRAINT `chk``price` CHECK (price <= ceiling)',
            'ALTER TABLE `prod``ucts` RENAME TO `renamed`',
        ], [...(new MySQLCompiler((new DriverInfo($driver, $version))->getCapabilities()))->compile($plan)]);
    }

    public function testCheckChangesStayBetweenSurroundingDataStatements(): void
    {
        $plan = new Plan();
        $plan->raw('UPDATE products SET price = 0 WHERE price < 0');
        $plan->alter('products')->addCheck('chk_price', 'price >= 0');
        $plan->raw('INSERT INTO products (price) VALUES (1)');
        $plan->alter('products')->dropCheck('chk_price');
        $plan->raw('INSERT INTO products (price) VALUES (-1)');

        self::assertSame([
            'UPDATE products SET price = 0 WHERE price < 0',
            'ALTER TABLE `products` ADD CONSTRAINT `chk_price` CHECK (price >= 0)',
            'INSERT INTO products (price) VALUES (1)',
            'ALTER TABLE `products` DROP CHECK `chk_price`',
            'INSERT INTO products (price) VALUES (-1)',
        ], [...(new MySQLCompiler((new DriverInfo('mysql', '8.4.0'))->getCapabilities()))->compile($plan)]);
    }

    public function testStandaloneOperationsAreCompiledInPlace(): void
    {
        $plan = new Plan();
        $plan->add(new AddCheck('products', 'chk_price', 'price >= 0'));
        $plan->add(new DropCheck('products', 'chk_price'));

        self::assertSame([
            'ALTER TABLE `products` ADD CONSTRAINT `chk_price` CHECK (price >= 0)',
            'ALTER TABLE `products` DROP CONSTRAINT `chk_price`',
        ], [...(new MySQLCompiler((new DriverInfo('mariadb', '10.5.0'))->getCapabilities()))->compile($plan)]);
    }

    public static function providerUnsupportedCapabilities(): iterable
    {
        foreach (['create', 'add', 'drop'] as $operation) {
            foreach ([
                ['mysql', '5.7.44'], ['mysql', '8.0.15'], ['mariadb', '10.2.0'],
                ['vitess', '8.0.30'], ['unknown', '99.0.0'], [null, null],
            ] as [$driver, $version]) {
                yield [$operation, $driver, $version];
            }
        }
    }

    /**
     * @dataProvider providerUnsupportedCapabilities
     */
    public function testUnsupportedOrMissingCapabilitiesRejectCheckSql(
        string $operation,
        ?string $driver,
        ?string $version,
    ): void {
        $capabilities = null === $driver ? null : (new DriverInfo($driver, $version))->getCapabilities();
        $plan = new Plan();
        match ($operation) {
            'create' => $plan->create('products')->addColumn('price', 'INT')->addCheck('chk_price', 'price >= 0'),
            'add' => $plan->alter('products')->addCheck('chk_price', 'price >= 0'),
            'drop' => $plan->alter('products')->dropCheck('chk_price'),
        };

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('capabilities confirming enforcement support');
        foreach ((new MySQLCompiler($capabilities))->compile($plan) as $statement) {
            self::fail('Unsupported CHECK SQL emitted: ' . $statement);
        }
    }

    public function testCustomCapabilitiesSelectDropConstraintWithoutConcreteClassChecks(): void
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('hasCheckConstraints')->willReturn(true);
        $capabilities->method('hasDropCheck')->willReturn(false);
        $plan = new Plan();
        $plan->alter('products')->dropCheck('chk_price');

        self::assertSame(['ALTER TABLE `products` DROP CONSTRAINT `chk_price`'], [
            ...(new MySQLCompiler($capabilities))->compile($plan),
        ]);
    }

    public function testSqliteOfflineCreateIsSupported(): void
    {
        $plan = new Plan();
        $plan->create('products')->addColumn('price', 'INT')->addCheck('0', '0');

        self::assertStringContainsString('CONSTRAINT "0" CHECK (0)', [...(new SqliteCompiler())->compile($plan)][0]);
    }

    public function testSqliteExplicitUnsupportedCapabilitiesAreRespected(): void
    {
        $plan = new Plan();
        $plan->create('products')->addColumn('price', 'INT')->addCheck('chk_price', 'price >= 0');

        $this->expectException(PlanException::class);
        [...(new SqliteCompiler(new UnknownCapabilities()))->compile($plan)];
    }

    public static function providerSqliteAlterContexts(): iterable
    {
        foreach (['add', 'drop'] as $operation) {
            foreach (['no schema', 'missing table', 'unknown checks', 'complete'] as $context) {
                yield [$operation, $context];
            }
        }
    }

    /**
     * @dataProvider providerSqliteAlterContexts
     */
    public function testSqliteCheckAlterCannotBeSilentlyIgnored(string $operation, string $context): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products',
            columns: ['price' => new Column('price', 0, null, true, 'int')],
            checks: 'unknown checks' === $context ? null : []);
        $schema = 'no schema' === $context ? null : new Schema('default', 'main', 'utf8',
            tables: 'missing table' === $context ? [] : ['products' => $table]);
        $plan = new Plan();
        $alter = $plan->alter('products');
        'add' === $operation ? $alter->addCheck('chk_price', 'price >= 0') : $alter->dropCheck('chk_price');
        // Also exercise the existing rebuild path, where unhandled operations were previously ignored.
        $alter->modifyColumn('price', 'INTEGER', nullable: true);

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage(match ($context) {
            'no schema' => 'existing schema',
            'missing table' => 'missing from the schema',
            'unknown checks' => 'complete CHECK metadata',
            'complete' => 'requires table rebuild support',
        });
        foreach ((new SqliteCompiler())->compile($plan, $schema) as $statement) {
            self::fail('CHECK alteration emitted an incomplete rebuild: ' . $statement);
        }
    }

    public function testStandaloneSqliteCheckAlterRequiresRebuild(): void
    {
        $plan = new Plan();
        $plan->add(new AddCheck('products', 'chk_price', 'price >= 0'));

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('existing schema');
        [...(new SqliteCompiler())->compile($plan)];
    }

    public static function providerUnsupportedAutoDrivers(): array
    {
        return [['vitess', '8.0.30'], ['unknown', '99.0.0'], ['pgsql', '17.0']];
    }

    /**
     * @dataProvider providerUnsupportedAutoDrivers
     */
    public function testAutoCompilerRejectsUnsupportedEngines(string $driver, string $version): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo($driver, $version));
        $plan = new Plan();
        $plan->create('products')->addColumn('price', 'INT')->addCheck('chk_price', 'price >= 0');

        $this->expectException(PlanException::class);
        [...(new AutoCompiler($connection))->compile($plan)];
    }
}
