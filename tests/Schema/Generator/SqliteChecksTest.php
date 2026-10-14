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
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Generator\Sqlite\CreateTableParser;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

class SqliteChecksTest extends TestCase
{
    public static function providerDefinitions(): array
    {
        return [
            'named table and anonymous column' => [
                'CREATE TABLE items (price INT CHECK (price >= 0), starts_at INT, ends_at INT, ' .
                'CONSTRAINT chk_period CHECK (ends_at >= starts_at))',
                [
                    ['name' => null, 'expression' => 'price >= 0'],
                    ['name' => 'chk_period', 'expression' => 'ends_at >= starts_at'],
                ],
            ],
            'nested parentheses and comma' => [
                'CREATE TABLE items (price DECIMAL(10,2), CHECK (coalesce((abs(price)), 0) >= 0))',
                [['name' => null, 'expression' => 'coalesce((abs(price)), 0) >= 0']],
            ],
            'multiple anonymous checks on one column' => [
                'CREATE TABLE items (price INT CHECK (price >= 0) CHECK (price < 100), CHECK (price <> 42))',
                [
                    ['name' => null, 'expression' => 'price >= 0'],
                    ['name' => null, 'expression' => 'price < 100'],
                    ['name' => null, 'expression' => 'price <> 42'],
                ],
            ],
            'quoted names and literals' => [
                <<<'SQL'
                CREATE TABLE items (
                    "CHECK" TEXT,
                    CONSTRAINT "chk""one" CHECK ("CHECK" <> 'it''s CHECK (x,y)'),
                    CONSTRAINT `chk``two` CHECK (length("CHECK") > 0),
                    CONSTRAINT [chk three] CHECK ("CHECK" <> '/* ) */'),
                    CONSTRAINT 'chk''four' CHECK ("CHECK" <> '-- )')
                )
                SQL,
                [
                    ['name' => 'chk"one', 'expression' => '"CHECK" <> \'it\'\'s CHECK (x,y)\''],
                    ['name' => 'chk`two', 'expression' => 'length("CHECK") > 0'],
                    ['name' => 'chk three', 'expression' => '"CHECK" <> \'/* ) */\''],
                    ['name' => "chk'four", 'expression' => '"CHECK" <> \'-- )\''],
                ],
            ],
            'comments between and inside tokens' => [
                "CREATE TABLE items (price INT, CONSTRAINT /* CHECK(x) */ chk_price CHECK -- (\n" .
                "( price /* ) , CHECK */ >= 0 -- )\n ))",
                [['name' => 'chk_price', 'expression' => " price /* ) , CHECK */ >= 0 -- )\n "]],
            ],
            'constraint name applies to the next constraint only' => [
                'CREATE TABLE items (price INT CONSTRAINT nn NOT NULL CHECK (price >= 0) ' .
                'CONSTRAINT chk_upper CHECK (price < 100) CHECK (price <> 42))',
                [
                    ['name' => null, 'expression' => 'price >= 0'],
                    ['name' => 'chk_upper', 'expression' => 'price < 100'],
                    ['name' => null, 'expression' => 'price <> 42'],
                ],
            ],
            'duplicate names are retained' => [
                'CREATE TABLE items (price INT, CONSTRAINT chk CHECK (price >= 0), CONSTRAINT chk CHECK (price < 100))',
                [
                    ['name' => 'chk', 'expression' => 'price >= 0'],
                    ['name' => 'chk', 'expression' => 'price < 100'],
                ],
            ],
            'quoted constraint name CHECK is not a keyword' => [
                'CREATE TABLE items (price INT CONSTRAINT "CHECK" NOT NULL, ' .
                'CONSTRAINT "CONSTRAINT" CHECK (price >= 0))',
                [['name' => 'CONSTRAINT', 'expression' => 'price >= 0']],
            ],
            'defaults and generated expressions are not CHECKs' => [
                <<<'SQL'
                CREATE TABLE items (
                    "CHECK" TEXT DEFAULT ('CHECK (x)'),
                    price INT,
                    v INT AS (length('CHECK (price)')),
                    CONSTRAINT pk PRIMARY KEY (price)
                )
                SQL,
                [],
            ],
            'zero and NULL are still expressions' => [
                'CREATE TABLE items (price INT, CHECK (0), CHECK (NULL))',
                [['name' => null, 'expression' => '0'], ['name' => null, 'expression' => 'NULL']],
            ],
            'qualified name and if not exists' => [
                'CREATE TABLE IF NOT EXISTS "main"."items" (price INT, CHECK (price >= 0))',
                [['name' => null, 'expression' => 'price >= 0']],
            ],
            'trailing table options' => [
                'CREATE TABLE items (price INTEGER PRIMARY KEY, CHECK (price >= 0)) WITHOUT ROWID',
                [['name' => null, 'expression' => 'price >= 0']],
            ],
        ];
    }

    /**
     * @dataProvider providerDefinitions
     */
    public function testParsingAndDatabaseIntrospection(string $sql, array $expected): void
    {
        self::assertSame($expected, (new CreateTableParser())->getChecks($sql));

        $connection = new Connection('sqlite::memory:');
        $connection->execute($sql);
        $schema = (new Sqlite($connection))->generateSchema('main');
        /** @var Schema $restored */
        $restored = unserialize(serialize($schema));

        foreach ([$schema, $restored] as $result) {
            $table = $result->getTable('items');
            self::assertTrue($table->hasCheckMetadata());
            $actual = [];
            foreach ($table->getChecks() as $check) {
                self::assertSame($table, $check->getTable());
                self::assertTrue($check->isEnforced());
                $actual[] = ['name' => $check->getName(), 'expression' => $check->getExpression()];
            }
            self::assertSame($expected, $actual);
        }
    }

    public static function providerMalformedDefinitions(): array
    {
        return [
            ['CREATE TABLE items (price INT CHECK ())'],
            ['CREATE TABLE items (price INT CHECK (/* empty */))'],
            ['CREATE TABLE items (price INT CHECK (price > 0)'],
            ['CREATE TABLE items (price INT CHECK price > 0)'],
            ["CREATE TABLE items (price TEXT CHECK (price <> 'unterminated))"],
            ['CREATE TABLE items (price INT CHECK (price > 0 /* unterminated))'],
            ['CREATE TABLE items'],
            ['CREATE TABLE items AS SELECT abs(1)'],
            ['CREATE VIEW items AS SELECT 1'],
            ['CREATE TABLE IF EXISTS items (price INT)'],
        ];
    }

    /**
     * @dataProvider providerMalformedDefinitions
     */
    public function testMalformedSqlCannotBeMistakenForAnEmptyCheckList(string $sql): void
    {
        $this->expectException(SchemaException::class);
        (new CreateTableParser())->getChecks($sql);
    }

    public function testViewHasKnownEmptyMetadata(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->execute('CREATE VIEW items AS SELECT \'CHECK (x)\' AS label');
        $generator = new FakeSqlite($connection);

        self::assertSame([], $generator->getChecksInfo('main', 'items'));
        $table = $generator->generateSchema('main')->getTable('items');
        self::assertTrue($table->hasCheckMetadata());
        self::assertSame([], iterator_to_array($table->getChecks()));
    }

    public function testVirtualTableModuleArgumentsAreNotParsedAsOrdinaryDefinitions(): void
    {
        $sql = 'CREATE VIRTUAL TABLE items USING fts5(label, tokenize = "porter")';
        self::assertNull((new CreateTableParser())->getChecks($sql));
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(['type' => 'table', 'sql' => $sql]);

        self::assertNull((new FakeSqlite($connection))->getChecksInfo('main', 'items'));
    }

    public function testMissingTableSqlFailsClearly(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(['type' => 'table', 'sql' => null]);

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Missing SQLite CREATE TABLE SQL');
        (new FakeSqlite($connection))->getChecksInfo('main', 'items');
    }
}
