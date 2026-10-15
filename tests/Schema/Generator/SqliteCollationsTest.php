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
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Generator\Sqlite\CreateTableParser;
use PHPUnit\Framework\TestCase;

class SqliteCollationsTest extends TestCase
{
    /** @dataProvider definitions */
    public function testExtractionAndIntrospection(string $sql, array $expected): void
    {
        $this->assertSame($expected, (new CreateTableParser())->getColumnCollations($sql));
        $connection = new Connection('sqlite::memory:');
        $connection->getPdo()->sqliteCreateCollation('custom"name', 'strcmp');
        $connection->execute($sql);
        $table = unserialize(serialize((new Sqlite($connection))->generateSchema('main')))->getTable('items');
        foreach ($table->getColumns() as $column) {
            $this->assertSame($expected[$column->getName()] ?? null, $column->getCollation());
        }
    }

    public static function definitions(): array
    {
        return [
            'ordinary and quoted names' => [
                <<<'SQL'
                CREATE TABLE items (
                    plain TEXT, a TEXT COLLATE NOCASE, b TEXT COLLATE "RTRIM",
                    c TEXT COLLATE `BINARY`, d TEXT COLLATE [NOCASE], e TEXT COLLATE 'RTRIM',
                    "quoted""column" TEXT COLLATE "custom""name", number INTEGER COLLATE NOCASE
                )
                SQL,
                ['a' => 'NOCASE', 'b' => 'RTRIM', 'c' => 'BINARY', 'd' => 'NOCASE', 'e' => 'RTRIM',
                    'quoted"column' => 'custom"name', 'number' => 'NOCASE'],
            ],
            'comments, expressions and constraints' => [
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS main.items (
                    "COLLATE" TEXT DEFAULT 'COLLATE NOCASE',
                    label TEXT COLLATE /* COLLATE BINARY */ NOCASE CHECK(label COLLATE RTRIM <> ''),
                    generated TEXT AS (label COLLATE BINARY) STORED COLLATE RTRIM,
                    implicit TEXT AS (label COLLATE NOCASE),
                    other TEXT DEFAULT ('COLLATE RTRIM' COLLATE NOCASE),
                    last TEXT COLLATE BINARY COLLATE NOCASE,
                    CONSTRAINT quoted_check CHECK (label COLLATE BINARY <> 'x'),
                    UNIQUE(other COLLATE RTRIM)
                )
                SQL,
                ['label' => 'NOCASE', 'generated' => 'RTRIM', 'last' => 'NOCASE'],
            ],
            'named column constraint' => [
                'CREATE TABLE items (label TEXT CONSTRAINT "COLLATE" COLLATE NOCASE)',
                ['label' => 'NOCASE'],
            ],
        ];
    }

    public function testViewsAndVirtualDeclarationsAreNotParsedAsOrdinaryTables(): void
    {
        $parser = new CreateTableParser();
        $this->assertSame([], $parser->getColumnCollations('CREATE VIEW items AS SELECT label COLLATE NOCASE FROM t'));
        $this->assertSame([], $parser->getColumnCollations('CREATE VIRTUAL TABLE items USING fts5(label)'));

        $connection = new Connection('sqlite::memory:');
        $connection->execute('CREATE TABLE items (label TEXT COLLATE NOCASE)');
        $connection->execute('CREATE VIEW item_view AS SELECT label FROM items');
        $schema = (new Sqlite($connection))->generateSchema('main');
        $this->assertSame('NOCASE', $schema->getTable('items')->getColumn('label')->getCollation());
        $this->assertNull($schema->getTable('item_view')->getColumn('label')->getCollation());
    }

    public function testIndexCollationStillPreventsLossyRebuilds(): void
    {
        $parser = new CreateTableParser();
        $this->assertSame([], $parser->getUnsupportedRebuildFeatures('CREATE TABLE t (value TEXT COLLATE NOCASE)'));
        $this->assertContains('COLLATE', $parser->getUnsupportedRebuildFeatures(
            'CREATE TABLE t (value TEXT, UNIQUE(value COLLATE NOCASE))',
        ));
    }
}
