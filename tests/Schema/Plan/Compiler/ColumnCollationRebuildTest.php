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
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PDOException;
use PHPUnit\Framework\TestCase;

class ColumnCollationRebuildTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute('CREATE TABLE items (' .
            'reference TEXT COLLATE NOCASE UNIQUE, quantity INT, ' .
            'derived TEXT AS (reference) STORED COLLATE RTRIM)');
        $this->connection->execute("INSERT INTO items(reference, quantity) VALUES ('ABC', 1)");
    }

    private function schema(): Schema
    {
        return unserialize(serialize((new Sqlite($this->connection))->generateSchema('main')));
    }

    private function execute(Plan $plan, ?Schema $schema = null): void
    {
        foreach ([...$plan->getStatements(new SqliteCompiler(), $schema ?? $this->schema())] as $statement) {
            $this->connection->execute($statement);
        }
    }

    private function assertDuplicateRejected(string $column): void
    {
        try {
            $this->connection->execute('INSERT INTO items("' . $column . '") VALUES (\'abc\')');
            $this->fail('The NOCASE unique constraint must reject a case variant');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
        }
    }

    public function testUnrelatedAlterAndRenamePreserveComparisonAndUniqueness(): void
    {
        $this->assertDuplicateRejected('reference');
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('quantity', 'BIGINT', nullable: true);
        $this->execute($plan);
        $this->assertDuplicateRejected('reference');
        $this->assertSame(['ABC'], $this->connection->fetchColumn("SELECT reference FROM items WHERE reference = 'abc'"));
        $this->assertSame(['ABC'], $this->connection->fetchColumn("SELECT derived FROM items WHERE derived = 'ABC '"));

        $rename = new Plan();
        $rename->alter('items')->renameColumn('reference', 'code')->modifyColumn('quantity', 'INTEGER', nullable: true);
        $this->execute($rename);
        $this->assertDuplicateRejected('code');
        $table = $this->schema()->getTable('items');
        $this->assertSame('NOCASE', $table->getColumn('code')->getCollation());
        $this->assertSame('RTRIM', $table->getColumn('derived')->getCollation());
        $this->assertSame(['ABC'], $this->connection->fetchColumn("SELECT derived FROM items WHERE derived = 'ABC '"));
    }

    public function testModifyChangesCollationAndOmissionRestoresSqliteDefault(): void
    {
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('reference', 'VARCHAR(64)', charset: 'ascii', collation: 'BINARY');
        $this->execute($plan);
        $this->connection->execute("INSERT INTO items(reference) VALUES ('abc')");
        $this->assertSame(['abc'], $this->connection->fetchColumn("SELECT reference FROM items WHERE reference = 'abc'"));
        $this->assertSame('BINARY', $this->schema()->getTable('items')->getColumn('reference')->getCollation());

        $remove = new Plan();
        $remove->alter('items')->modifyColumn('reference', 'TEXT');
        $this->execute($remove);
        $this->assertNull($this->schema()->getTable('items')->getColumn('reference')->getCollation());
        $this->assertSame(['abc'], $this->connection->fetchColumn("SELECT reference FROM items WHERE reference = 'abc'"));
    }

    public function testStoredAdditionRetainsNewAndExistingCollations(): void
    {
        $plan = new Plan();
        $plan->alter('items')->addColumn('computed', 'TEXT', generated: new Generated('reference', stored: true),
            charset: 'ascii', collation: 'NOCASE');
        $this->execute($plan);
        $this->assertSame('NOCASE', $this->schema()->getTable('items')->getColumn('computed')->getCollation());
        $this->assertSame(['ABC'], $this->connection->fetchColumn("SELECT computed FROM items WHERE computed = 'abc'"));
        $this->assertDuplicateRejected('reference');
    }

    public function testSourceDefinitionPreservesCollationFromOlderMetadata(): void
    {
        $schema = $this->schema();
        foreach ($schema->getTable('items')->getColumns() as $column) {
            $data = $column->__serialize();
            $data['collation'] = null;
            $column->__unserialize($data);
        }
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('quantity', 'INTEGER', nullable: true);
        $this->execute($plan, $schema);
        $this->assertSame('NOCASE', $this->schema()->getTable('items')->getColumn('reference')->getCollation());
        $this->assertDuplicateRejected('reference');
    }

    public function testTextOptionModificationRequiresSchema(): void
    {
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('requires a SQLite table rebuild with an existing schema');
        $plan = new Plan();
        $plan->add(new ModifyColumn('items', 'reference', 'TEXT', collation: 'BINARY'));
        foreach ($plan->getStatements(new SqliteCompiler()) as $statement) {
            $this->fail('Unsupported ALTER emitted: ' . $statement);
        }
    }
}
