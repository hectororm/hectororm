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
use Hector\Connection\Driver\SQLiteCapabilities;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\DialectInterface;
use Hector\Schema\Plan\Compiler\Dialect\MySQLDialect;
use Hector\Schema\Plan\Compiler\Dialect\SqliteDialect;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class GeneratedColumnSqlTest extends TestCase
{
    /**
     * @dataProvider legacyRenameModes
     */
    public function testLegacyRenamePreservesGeneratedMetadata(bool $mariaDb, bool $stored): void
    {
        $dialect = new MySQLDialect($mariaDb
            ? new MariaDBCapabilities(new DriverInfo('mariadb', '10.5.1'))
            : new MySQLCapabilities(new DriverInfo('mysql', '5.7.44')));
        $expression = 'coalesce(`price`, 0) * `quantity`';
        $column = new Column('total', 0, null, true, 'decimal', numeric_precision: 10, numeric_scale: 2,
            generation_expression: $expression, generated_stored: $stored);
        $table = new Table(schema_name: 'main', name: 'items', type: Table::TYPE_TABLE, columns: ['total' => $column]);
        $schema = new Schema(connection: 'default', name: 'main', charset: 'utf8mb4', tables: ['items' => $table]);
        $alter = new AlterTable('items');
        $alter->renameColumn('total', 'renamed_total');

        $mode = $stored ? 'STORED' : 'VIRTUAL';
        $nullability = $mariaDb ? '' : ' NULL';
        $this->assertSame([
            'ALTER TABLE `items` CHANGE COLUMN `total` `renamed_total` decimal(10,2) '
            . "GENERATED ALWAYS AS ($expression) $mode$nullability",
        ], [...$dialect->compileAlterTable($alter, new CompilationContext($schema))]);
    }

    public static function legacyRenameModes(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    private function dialect(string $driver): DialectInterface
    {
        return match ($driver) {
            'mysql' => new MySQLDialect(new MySQLCapabilities(new DriverInfo('mysql', '5.7.44'))),
            'mariadb' => new MySQLDialect(new MariaDBCapabilities(new DriverInfo('mariadb', '10.5.29'))),
            'sqlite' => new SqliteDialect(new SQLiteCapabilities(new DriverInfo('sqlite', '3.31.0'))),
        };
    }

    /**
     * @dataProvider definitions
     */
    public function testCreateGeneratedColumn(string $driver, bool $stored, bool $nullable): void
    {
        $create = new CreateTable('items');
        $create->addColumn('quantity', 'INTEGER');
        $create->addColumn('price', 'INTEGER');
        $create->addColumn('total', 'INTEGER', nullable: $nullable,
            generated: new Generated('quantity * price', stored: $stored));

        $quote = 'sqlite' === $driver ? '"' : '`';
        $mode = $stored ? 'STORED' : 'VIRTUAL';
        $nullability = match ($driver) {
            'mariadb' => '',
            'sqlite' => $nullable ? '' : ' NOT NULL',
            default => $nullable ? ' NULL' : ' NOT NULL',
        };
        $this->assertSame([
            "CREATE TABLE {$quote}items{$quote} (\n"
            . "  {$quote}quantity{$quote} INTEGER NOT NULL,\n"
            . "  {$quote}price{$quote} INTEGER NOT NULL,\n"
            . "  {$quote}total{$quote} INTEGER GENERATED ALWAYS AS (quantity * price) $mode$nullability\n)",
        ], [...$this->dialect($driver)->compileCreateTable($create)]);
    }

    public static function definitions(): iterable
    {
        foreach (['mysql', 'mariadb', 'sqlite'] as $driver) {
            foreach ([false, true] as $stored) {
                foreach ([false, true] as $nullable) {
                    yield [$driver, $stored, $nullable];
                }
            }
        }
    }

    public function testMysqlAddAndModifyPreservePlacementAndStorage(): void
    {
        $dialect = $this->dialect('mysql');
        $alter = new AlterTable('items');
        $alter->addColumn('total', 'INTEGER', generated: 'quantity * price', after: 'price');
        $alter->modifyColumn('other_total', 'INTEGER', nullable: true, first: true,
            generated: new Generated('quantity * (price + 1)', stored: true));

        $this->assertSame([
            'ALTER TABLE `items` ADD COLUMN `total` INTEGER '
            . 'GENERATED ALWAYS AS (quantity * price) VIRTUAL NOT NULL AFTER `price`, '
            . 'MODIFY COLUMN `other_total` INTEGER '
            . 'GENERATED ALWAYS AS (quantity * (price + 1)) STORED NULL FIRST',
        ], [...$dialect->compileAlterTable($alter, new CompilationContext())]);
    }

    public function testMariaDbModifyOmitsNullability(): void
    {
        $operation = new ModifyColumn('items', 'total', 'INTEGER',
            generated: new Generated('quantity * price', stored: true));

        $this->assertSame([
            'ALTER TABLE `items` MODIFY COLUMN `total` INTEGER '
            . 'GENERATED ALWAYS AS (quantity * price) STORED',
        ], [...$this->dialect('mariadb')->compileStandaloneOperation($operation)]);
    }

    public function testSqliteVirtualAddPreservesExpressionVerbatim(): void
    {
        $expression = "CASE WHEN label = 'a,b (c)' THEN (quantity * price) ELSE 0 END";
        $operation = new AddColumn('items', 'total"amount', 'INTEGER', nullable: true, generated: $expression);

        $this->assertSame([
            'ALTER TABLE "items" ADD COLUMN "total""amount" INTEGER '
            . "GENERATED ALWAYS AS ($expression) VIRTUAL",
        ], [...$this->dialect('sqlite')->compileStandaloneOperation($operation)]);
    }

    /**
     * @dataProvider rebuildOperations
     */
    public function testSqliteCannotEmitUnsupportedNativeAlter(string $operation, bool $standalone): void
    {
        $generated = new Generated('quantity * price', stored: 'add' === $operation);
        $alter = new AlterTable('items');
        if ('add' === $operation) {
            $alter->addColumn('total', 'INTEGER', generated: $generated);
        } else {
            $alter->modifyColumn('total', 'INTEGER', generated: $generated);
        }
        $column = $alter->getArrayCopy()[0];
        $dialect = $this->dialect('sqlite');

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('requires a SQLite table rebuild with an existing schema');

        $statements = $standalone
            ? $dialect->compileStandaloneOperation($column)
            : $dialect->compileAlterTable($alter, new CompilationContext());
        foreach ($statements as $statement) {
            $this->fail('Unsupported native ALTER emitted: ' . $statement);
        }
    }

    public static function rebuildOperations(): array
    {
        return [['add', true], ['add', false], ['modify', true], ['modify', false]];
    }

    public function testSqliteVersionRequirementOnlyAppliesToGeneratedColumns(): void
    {
        $capabilities = new SQLiteCapabilities(new DriverInfo('sqlite', '3.30.1'));
        $this->assertFalse($capabilities->hasGeneratedColumns());
        $dialect = new SqliteDialect($capabilities);
        $create = new CreateTable('items');
        $create->addColumn('quantity', 'INTEGER');
        $this->assertCount(1, [...$dialect->compileCreateTable($create)]);

        $create->addColumn('total', 'INTEGER', generated: 'quantity * 2');
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('Generated columns require SQLite 3.31.0 or newer');
        $dialect->compileCreateTable($create);
    }
}
