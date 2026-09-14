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

namespace Hector\Schema\Tests\Generator;

use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverInfo;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Generator\Sqlite\CreateTableParser;
use PHPUnit\Framework\TestCase;

class GeneratedColumnsTest extends TestCase
{
    /**
     * @dataProvider renamedExpressions
     */
    public function testRenameChangesOnlyColumnReferences(
        string $expression,
        string $oldName,
        string $newName,
        string $expected,
    ): void {
        $this->assertSame($expected,
            (new CreateTableParser())->renameColumnReferences($expression, $oldName, $newName));
    }

    public static function renamedExpressions(): array
    {
        return [
            'identifiers and prefixes' => [
                'price + "PRICE" + [price] + `price` + priced', 'price', 'unit_price',
                '"unit_price" + "unit_price" + "unit_price" + "unit_price" + priced',
            ],
            'function name' => ['abs(abs) + abs', 'abs', 'value', 'abs("value") + "value"'],
            'nested CAST types' => [
                'CAST(real AS real) + CAST(CAST(real AS TEXT) AS real)', 'real', 'value',
                'CAST("value" AS real) + CAST(CAST("value" AS TEXT) AS real)',
            ],
            'multiword CAST type' => [
                'CAST(q AS DOUBLE PRECISION) + precision', 'precision', 'value',
                'CAST(q AS DOUBLE PRECISION) + "value"',
            ],
            'collation name' => [
                'label COLLATE nocase || nocase', 'nocase', 'value', 'label COLLATE nocase || "value"',
            ],
            'blob prefix' => ["X'78' || x || 'x'", 'x', 'value', "X'78' || \"value\" || 'x'"],
            'numeric literals' => ['1.e+2 + e + 0xe', 'e', 'value', '1.e+2 + "value" + 0xe'],
            'numeric identifier' => ["0 + \"0\" + '0'", '0', 'value', "0 + \"value\" + '0'"],
            'strings and escaped new identifier' => [
                "'price' || \"price\" || 'it''s price'", 'price', 'unit"price',
                "'price' || \"unit\"\"price\" || 'it''s price'",
            ],
            'comments' => [
                "price /* price */ + price -- price\n", 'price', 'value',
                "\"value\" /* price */ + \"value\" -- price\n",
            ],
        ];
    }

    public function testAmbiguousKeywordRenameFailsInsteadOfRewritingSqlSyntax(): void
    {
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('Cannot safely rewrite unquoted keyword "end"');
        (new CreateTableParser())->renameColumnReferences('CASE WHEN q > 0 THEN q ELSE 0 END', 'end', 'ending');
    }

    /**
     * @dataProvider sqliteDefinitions
     */
    public function testSqliteExpressionExtractionAndIntrospection(string $sql, array $expected): void
    {
        $this->assertSame($expected, (new CreateTableParser())->getGeneratedExpressions($sql));

        $connection = new Connection('sqlite::memory:');
        $connection->execute($sql);
        $table = (new Sqlite($connection))->generateSchema('main')->getTable('items');
        $actual = [];
        foreach ($table->getColumns() as $column) {
            if ($column->isGenerated()) {
                $actual[$column->getName()] = $column->getGenerationExpression();
                $this->assertFalse($column->hasDefault());
                $this->assertFalse($column->isAutoIncrement());
            }
        }
        $this->assertSame($expected, $actual);
    }

    public static function sqliteDefinitions(): array
    {
        return [
            'both modes and interspersed columns' => [
                'CREATE TABLE items (q INT, v INT AS (q * 2), p INT, s INT GENERATED ALWAYS AS (q * p) STORED)',
                ['v' => 'q * 2', 's' => 'q * p'],
            ],
            'nested expressions and commas' => [
                'CREATE TABLE items (q INT, p DECIMAL(10,2), v AS (coalesce((q + abs(p)), 0)))',
                ['v' => 'coalesce((q + abs(p)), 0)'],
            ],
            'quotes and constraint-like column names' => [
                <<<'SQL'
                CREATE TABLE items (
                    "AS" INT,
                    "a""b" TEXT AS ('it''s (a,b) -- AS (' || CAST("AS" AS TEXT)),
                    `a``b` INT AS ("AS" + 1),
                    [a,b] INT AS ("AS" + 2),
                    'single''quote' INT AS ("AS" + 3),
                    "CHECK" INT AS (0),
                    CONSTRAINT valid CHECK ("AS" >= 0)
                )
                SQL,
                [
                    'a"b' => "'it''s (a,b) -- AS (' || CAST(\"AS\" AS TEXT)",
                    'a`b' => '"AS" + 1',
                    'a,b' => '"AS" + 2',
                    "single'quote" => '"AS" + 3',
                    'CHECK' => '0',
                ],
            ],
            'comments between tokens and inside expressions' => [
                "CREATE TABLE items (q INT, v INT AS /* ( AS , */ ( q /* ) , */ + 1 -- ) , AS (\n ) STORED)",
                ['v' => " q /* ) , */ + 1 -- ) , AS (\n "],
            ],
            'ordinary defaults and constraints are not generated' => [
                "CREATE TABLE items (q INT DEFAULT (CAST('1' AS INT)), p TEXT DEFAULT 'AS (q)', CHECK(q > 0))",
                [],
            ],
            'constant NULL is still generated' => [
                'CREATE TABLE items (q INT, v TEXT AS (NULL), s INT AS (0) STORED)',
                ['v' => 'NULL', 's' => '0'],
            ],
        ];
    }

    /**
     * @dataProvider malformedDefinitions
     */
    public function testMalformedSqlFailsExplicitly(string $sql): void
    {
        $this->expectException(SchemaException::class);
        (new CreateTableParser())->getGeneratedExpressions($sql);
    }

    public static function malformedDefinitions(): array
    {
        return [
            ['CREATE TABLE items (q INT, v INT AS ((q + 1))'],
            ['CREATE TABLE items (q INT, v TEXT AS (\'unterminated))'],
            ['CREATE TABLE items (q INT /* unterminated)'],
            ['CREATE TABLE items (q INT, v INT AS ( ))'],
            ['CREATE TABLE items'],
        ];
    }

    public function testMissingExpressionDoesNotTurnGeneratedColumnIntoOrdinaryColumn(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAll')->willReturn([
            ['name' => 'computed', 'hidden' => 2],
        ]);
        $connection->method('fetchOne')->willReturn(['sql' => 'CREATE TABLE items (computed INT)']);
        $generator = new FakeSqlite($connection);

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Cannot extract generation expression for column "computed" on table "items"');
        $generator->getColumnsInfo('main', 'items');
    }

    public function testHiddenVirtualTableColumnsAreExcluded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAll')->with("PRAGMA table_xinfo('items');")->willReturn([
            ['name' => 'hidden_internal', 'hidden' => 1],
            ['name' => 'q', 'hidden' => 0, 'type' => 'INT', 'cid' => 0,
                'pk' => 0, 'notnull' => 0, 'dflt_value' => null],
        ]);
        $connection->method('fetchOne')->willReturn(['sql' => 'CREATE VIRTUAL TABLE items USING module(q)']);
        $columns = (new FakeSqlite($connection))->getColumnsInfo('main', 'items');

        $this->assertSame(['q'], array_column($columns, 'name'));
        $this->assertNull($columns[0]['generation_expression']);
        $this->assertFalse($columns[0]['generated_stored']);
    }

    public function testOldSqliteFallsBackToTableInfo(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('fetchAll')->willReturnCallback(
            static fn(string $sql): array => match ($sql) {
                "PRAGMA table_xinfo('items');" => [],
                "PRAGMA table_info('items');" => [[
                    'name' => 'q', 'type' => 'INT', 'cid' => 0, 'pk' => 0, 'notnull' => 0, 'dflt_value' => null,
                ]],
            },
        );
        $connection->method('fetchOne')->willReturn(['sql' => 'CREATE TABLE items (q INT)']);

        $columns = (new FakeSqlite($connection))->getColumnsInfo('main', 'items');
        $this->assertSame(['q'], array_column($columns, 'name'));
        $this->assertNull($columns[0]['generation_expression']);
        $this->assertFalse($columns[0]['generated_stored']);
    }

    /**
     * @dataProvider mysqlGenerationMetadata
     */
    public function testMysqlGenerationMetadata(?string $expression, string $extra, bool $stored): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo('mysql', '8.0.30'));
        $connection->method('fetchAll')->willReturn([[
            'COLUMN_NAME' => 'computed', 'ORDINAL_POSITION' => 1, 'COLUMN_DEFAULT' => null,
            'IS_NULLABLE' => 'YES', 'DATA_TYPE' => 'int', 'COLUMN_TYPE' => 'int',
            'EXTRA' => $extra, 'GENERATION_EXPRESSION' => $expression,
            'CHARACTER_MAXIMUM_LENGTH' => null, 'NUMERIC_PRECISION' => 10, 'NUMERIC_SCALE' => 0,
            'CHARACTER_SET_NAME' => null, 'COLLATION_NAME' => null,
        ]]);
        $column = (new FakeMySQL($connection))->getColumnsInfo('main', 'items')[0];

        $this->assertSame('' === $expression ? null : $expression, $column['generation_expression']);
        $this->assertSame($stored, $column['generated_stored']);
    }

    public static function mysqlGenerationMetadata(): array
    {
        return [
            ['', 'DEFAULT_GENERATED', false],
            [null, '', false],
            ['0', 'VIRTUAL GENERATED', false],
            ['NULL', 'STORED GENERATED', true],
            ['`q` * 2', 'stored generated', true],
            ['`q` * 2', 'PERSISTENT', true],
        ];
    }
}
