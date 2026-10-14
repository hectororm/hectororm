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

namespace Hector\Schema\Tests\Plan\Compiler;

use Hector\Connection\Connection;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Operation\AddCheck;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PDOException;
use PHPUnit\Framework\TestCase;

class CheckRebuildTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
    }

    private function schema(): Schema
    {
        // Offline compilation must also retain SQLite source definitions after serialization.
        return unserialize(serialize((new Sqlite($this->connection))->generateSchema('main')));
    }

    private function execute(Plan $plan): array
    {
        $sql = [...(new SqliteCompiler())->compile($plan, $this->schema())];
        foreach ($sql as $statement) {
            $this->connection->execute($statement);
        }

        return $sql;
    }

    private function assertRejected(string $sql): void
    {
        try {
            $this->connection->execute($sql);
            self::fail('CHECK did not reject invalid data');
        } catch (PDOException $exception) {
            self::assertStringContainsString('CHECK constraint failed', $exception->getMessage());
        }
    }

    public function testAddDropAndReplaceKeepAnonymousChecksAndGeneratedColumns(): void
    {
        $this->connection->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, price INT CHECK (price >= 0), ' .
            'double_price INT AS (price * 2) STORED, CONSTRAINT upper_price CHECK (price < 100))');
        $this->connection->execute('INSERT INTO items(id, price) VALUES (3, 10)');
        $plan = new Plan();
        $plan->alter('items')->dropCheck('upper_price')->addCheck('upper_price', 'price < 50');
        $this->execute($plan);

        self::assertSame([['id' => 3, 'price' => 10, 'double_price' => 20]], $this->connection->fetchAll('SELECT * FROM items'));
        $checks = iterator_to_array($this->schema()->getTable('items')->getChecks());
        self::assertCount(2, $checks);
        self::assertNull($checks[0]->getName());
        self::assertSame('price >= 0', $checks[0]->getExpression());
        $this->assertRejected('INSERT INTO items(price) VALUES (-1)');
        $this->assertRejected('INSERT INTO items(price) VALUES (60)');

        $plan = new Plan();
        $plan->alter('items')->dropCheck('upper_price');
        $this->execute($plan);
        $this->connection->execute('INSERT INTO items(price) VALUES (60)');
        self::assertSame([120], $this->connection->fetchColumn('SELECT double_price FROM items WHERE price = 60'));
    }

    public function testUnrelatedRebuildPreservesChecksAndRewritesOnlyColumnReferences(): void
    {
        $this->connection->execute("CREATE TABLE items (price INT, label TEXT, " .
            "CHECK (price >= 0 AND label <> 'price'), CONSTRAINT upper_price CHECK (price < 100))");
        $this->connection->execute("INSERT INTO items(rowid, price, label) VALUES (99, 10, 'label')");
        $plan = new Plan();
        $plan->alter('items')->renameColumn('price', 'amount')->modifyColumn('label', 'TEXT', nullable: true)
            ->addColumn('double_amount', 'INT', generated: new Generated('amount * 2', stored: true));
        $this->execute($plan);

        self::assertSame([99], $this->connection->fetchColumn('SELECT rowid FROM items'));
        self::assertSame([20], $this->connection->fetchColumn('SELECT double_amount FROM items'));
        $checks = iterator_to_array($this->schema()->getTable('items')->getChecks());
        self::assertSame("\"amount\" >= 0 AND label <> 'price'", $checks[0]->getExpression());
        $this->assertRejected("INSERT INTO items(amount, label) VALUES (-1, 'label')");
        $this->assertRejected("INSERT INTO items(amount, label) VALUES (1, 'price')");
    }

    public function testCheckOnlyRebuildPreservesIndexesTriggersDefaultsAndSequence(): void
    {
        $this->connection->execute('CREATE TABLE audit (message TEXT)');
        $this->connection->execute("CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, " .
            "label TEXT COLLATE NOCASE UNIQUE DEFAULT ('hello'), price INT DEFAULT (abs(-2)))");
        $this->connection->execute('CREATE INDEX idx_price ON items ((price + 1)) WHERE price > 0');
        $this->connection->execute("CREATE TRIGGER audit_item AFTER INSERT ON items BEGIN " .
            "INSERT INTO audit VALUES (NEW.label); END");
        $this->connection->execute("INSERT INTO items(id, label) VALUES (100, 'deleted'), (3, 'kept')");
        $this->connection->execute('DELETE FROM items WHERE id = 100');
        $before = $this->connection->fetchAll("SELECT name, sql FROM sqlite_master " .
            "WHERE name IN ('idx_price', 'audit_item') ORDER BY name");
        $plan = new Plan();
        $plan->add(new AddCheck('items', 'positive', 'price >= 0'));
        $this->execute($plan);

        self::assertSame($before, $this->connection->fetchAll("SELECT name, sql FROM sqlite_master " .
            "WHERE name IN ('idx_price', 'audit_item') ORDER BY name"));
        self::assertSame([3], $this->connection->fetchColumn("SELECT id FROM items WHERE label = 'KEPT'"));
        self::assertSame([2], $this->connection->fetchColumn('SELECT count(*) FROM audit'));
        $this->connection->execute('INSERT INTO items DEFAULT VALUES');
        self::assertSame([101], $this->connection->fetchColumn("SELECT id FROM items WHERE label = 'hello'"));
        self::assertSame([2], $this->connection->fetchColumn("SELECT price FROM items WHERE label = 'hello'"));
        self::assertSame([3], $this->connection->fetchColumn('SELECT count(*) FROM audit'));
        $this->assertRejected("INSERT INTO items(label, price) VALUES ('bad', -1)");
    }

    public function testUnrelatedRebuildPreservesImplicitUniqueIndex(): void
    {
        $this->connection->execute('CREATE TABLE items (label TEXT UNIQUE, price INT CHECK(price >= 0))');
        $this->connection->execute("INSERT INTO items VALUES ('one', 1)");
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('price', 'INTEGER', nullable: true);
        $this->execute($plan);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('UNIQUE constraint failed');
        $this->connection->execute("INSERT INTO items VALUES ('one', 2)");
    }

    public function testWithoutRowidAndStrictOptionsSurviveCheckOnlyRebuild(): void
    {
        if (version_compare($this->connection->getDriverInfo()->getVersion(), '3.37.0', '<')) {
            $this->markTestSkipped('STRICT tables require SQLite 3.37 or newer');
        }
        $this->connection->execute('CREATE TABLE items (name TEXT PRIMARY KEY, price INTEGER) WITHOUT ROWID, STRICT');
        $this->connection->execute("INSERT INTO items VALUES ('one', 1)");
        $plan = new Plan();
        $plan->alter('items')->addCheck('positive', 'price >= 0');
        $this->execute($plan);

        $sql = $this->connection->fetchColumn("SELECT sql FROM sqlite_master WHERE name = 'items'")[0];
        self::assertStringContainsString('WITHOUT ROWID, STRICT', $sql);
        self::assertSame(['one'], $this->connection->fetchColumn('SELECT name FROM items'));
        $this->assertRejected("INSERT INTO items VALUES ('two', -1)");
    }

    public static function invalidChanges(): array
    {
        return [
            ['missing', 'missing or ambiguous'],
            ['duplicate', 'already exists'],
            ['ambiguous', 'missing or ambiguous'],
            ['drop column', 'Drop CHECK before dropping referenced column'],
            ['unknown', 'complete CHECK metadata'],
        ];
    }

    /** @dataProvider invalidChanges */
    public function testInvalidChangesFailBeforeAnyStatement(string $change, string $message): void
    {
        $this->connection->execute('CREATE TABLE items (price INT, other INT, CONSTRAINT positive CHECK(price >= 0)' .
            ('ambiguous' === $change ? ', CONSTRAINT positive CHECK(price < 100)' : '') . ')');
        $schema = $this->schema();
        $plan = new Plan();
        $alter = $plan->alter('items');
        match ($change) {
            'missing' => $alter->dropCheck('missing'),
            'duplicate' => $alter->addCheck('POSITIVE', 'price < 100'),
            'ambiguous' => $alter->dropCheck('positive'),
            'drop column' => $alter->dropColumn('price'),
            'unknown' => $alter->modifyColumn('other', 'TEXT'),
        };
        if ('unknown' === $change) {
            $table = $schema->getTable('items');
            $legacy = $table->__serialize();
            unset($legacy['checks']);
            $table->__unserialize($legacy);
        }

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage($message);
        foreach ((new SqliteCompiler())->compile($plan, $schema) as $statement) {
            self::fail('Emitted SQL before rejecting invalid rebuild: ' . $statement);
        }
    }

    public function testDroppingCheckBeforeItsColumnIsSupported(): void
    {
        $this->connection->execute('CREATE TABLE items (price INT, label TEXT, CONSTRAINT positive CHECK(price >= 0))');
        $this->connection->execute("INSERT INTO items VALUES (2, 'kept')");
        $plan = new Plan();
        $plan->alter('items')->dropCheck('positive')->dropColumn('price');
        $this->execute($plan);

        self::assertSame([['label' => 'kept']], $this->connection->fetchAll('SELECT * FROM items'));
        self::assertCount(0, iterator_to_array($this->schema()->getTable('items')->getChecks()));
    }

    public function testCheckOnlyRebuildPreservesForeignKeys(): void
    {
        $this->connection->execute('CREATE TABLE parents (id INTEGER PRIMARY KEY)');
        $this->connection->execute('CREATE TABLE items (price INT, parent_id INT, ' .
            'CONSTRAINT fk_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE)');
        $this->connection->execute('INSERT INTO parents VALUES (1)');
        $this->connection->execute('INSERT INTO items VALUES (10, 1)');
        $plan = new Plan();
        $plan->alter('items')->addCheck('positive', 'price >= 0');
        $this->execute($plan);

        self::assertSame([], $this->connection->fetchAll('PRAGMA foreign_key_check'));
        self::assertCount(1, $this->connection->fetchAll('PRAGMA foreign_key_list(items)'));
        $this->connection->execute('DELETE FROM parents WHERE id = 1');
        self::assertSame([0], $this->connection->fetchColumn('SELECT count(*) FROM items'));
    }

    public function testQuotedKeywordsAndCommentsSurviveReconstruction(): void
    {
        $this->connection->execute(<<<'SQL'
            CREATE TABLE items (
                "AUTOINCREMENT" INT,
                "CHECK" TEXT DEFAULT 'CHECK (x)',
                CONSTRAINT "chk""name" CHECK ("AUTOINCREMENT" >= 0 /* ), CHECK(foo) */)
            )
            SQL);
        $this->connection->execute('INSERT INTO items("AUTOINCREMENT") VALUES (1)');
        $plan = new Plan();
        $plan->alter('items')->addCheck('upper_bound', '"AUTOINCREMENT" < 100');
        $this->execute($plan);

        self::assertSame('"AUTOINCREMENT" >= 0 /* ), CHECK(foo) */',
            $this->schema()->getTable('items')->getCheck('chk"name')->getExpression());
        self::assertSame(['CHECK (x)'], $this->connection->fetchColumn('SELECT "CHECK" FROM items'));
        $this->assertRejected('INSERT INTO items("AUTOINCREMENT") VALUES (-1)');
    }

    public function testDescendingIntegerPrimaryKeyPreservesIndependentRowids(): void
    {
        $this->connection->execute('CREATE TABLE items (id INTEGER PRIMARY KEY DESC, price INT)');
        $this->connection->execute('INSERT INTO items(rowid, id, price) VALUES (99, 1, 2)');
        $plan = new Plan();
        $plan->alter('items')->addCheck('positive', 'price >= 0');
        $this->execute($plan);

        self::assertSame([['rowid' => 99, 'id' => 1]], $this->connection->fetchAll('SELECT rowid, id FROM items'));
    }

    public function testMixedAlterRejectsLossOfColumnCollationBeforeEmittingSql(): void
    {
        $this->connection->execute('CREATE TABLE items (label TEXT COLLATE NOCASE, price INT CHECK(price >= 0))');
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('price', 'INTEGER', nullable: true);

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('Cannot faithfully rebuild SQLite definitions with: COLLATE');
        foreach ((new SqliteCompiler())->compile($plan, $this->schema()) as $statement) {
            self::fail('Emitted lossy rebuild SQL: ' . $statement);
        }
    }

    public static function unsafeDependencies(): array
    {
        return [
            ['CREATE TRIGGER observe AFTER INSERT ON items BEGIN SELECT NEW.price; END', 'triggers'],
            ['CREATE INDEX idx ON items((price + 1))', 'expression/partial index'],
            ['CREATE INDEX idx ON items(price) WHERE price > 0', 'expression/partial index'],
            ['CREATE VIEW item_view AS SELECT * FROM items', 'dependent views'],
        ];
    }

    /** @dataProvider unsafeDependencies */
    public function testUnsafeDependencyRewritesAreRejected(string $objectSql, string $message): void
    {
        $this->connection->execute('CREATE TABLE items (price INT CHECK(price >= 0))');
        $this->connection->execute($objectSql);
        $plan = new Plan();
        $plan->alter('items')->renameColumn('price', 'amount')->addCheck('upper_price', 'amount < 100');

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage($message);
        $this->execute($plan);
    }

    public function testFailedCopyCanBeRolledBackWithoutLosingOriginalObjects(): void
    {
        $this->connection->execute('CREATE TABLE items (price INT CHECK(price < 100))');
        $this->connection->execute('INSERT INTO items VALUES (-1)');
        $this->connection->execute('CREATE INDEX idx ON items(price)');
        $original = $this->connection->fetchAll('SELECT name, sql FROM sqlite_master ORDER BY name');
        $plan = new Plan();
        $plan->alter('items')->addCheck('positive', 'price >= 0');
        $sql = [...(new SqliteCompiler())->compile($plan, $this->schema())];
        $this->connection->execute('PRAGMA foreign_keys = OFF');
        $this->connection->getPdo()->beginTransaction();
        try {
            foreach ($sql as $statement) {
                $this->connection->execute($statement);
            }
            self::fail('Copy should reject pre-existing invalid data');
        } catch (PDOException $exception) {
            self::assertStringContainsString('positive', $exception->getMessage());
        } finally {
            $this->connection->getPdo()->rollBack();
        }

        self::assertSame($original, $this->connection->fetchAll('SELECT name, sql FROM sqlite_master ORDER BY name'));
        self::assertSame([-1], $this->connection->fetchColumn('SELECT price FROM items'));
    }
}
