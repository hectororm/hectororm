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

use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\SqliteDialect;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\MigrateData;
use PHPUnit\Framework\TestCase;

/**
 * Class SqliteDialectTest.
 *
 * Unit tests targeting the SQLite dialect directly (not through the compiler).
 * The full table rebuild is covered by TableRebuilderTest and the execute tests.
 */
class SqliteDialectTest extends TestCase
{
    private function dialect(): SqliteDialect
    {
        return new SqliteDialect();
    }

    /**
     * @param iterable<string> $statements
     *
     * @return string[]
     */
    private function toArray(iterable $statements): array
    {
        return is_array($statements) ? array_values($statements) : iterator_to_array($statements, false);
    }

    public function testCapabilities(): void
    {
        $dialect = $this->dialect();

        $this->assertSame(['sqlite'], $dialect->driverNames());
        $this->assertFalse($dialect->supportsAlterForeignKey());
        $this->assertTrue($dialect->inlinesCreateTableForeignKeys());
    }

    public function testForeignKeyChecksUsePragma(): void
    {
        $dialect = $this->dialect();

        $this->assertSame('PRAGMA foreign_keys = OFF', $dialect->compileDisableForeignKeyChecks());
        $this->assertSame('PRAGMA foreign_keys = ON', $dialect->compileEnableForeignKeyChecks());
    }

    public function testCompileCreateTableInlinesForeignKeysAndSeparatesNonPrimaryIndexes(): void
    {
        $createTable = new CreateTable('posts');
        $createTable->addColumn('id', 'INTEGER', autoIncrement: true)
            ->addColumn('user_id', 'int')
            ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
            ->addIndex('idx_user', ['user_id'])
            ->addForeignKey('fk_user', ['user_id'], 'users', ['id'], onDelete: ForeignKey::RULE_CASCADE);

        $statements = $this->toArray($this->dialect()->compileCreateTable($createTable));

        // FK is inlined into the CREATE TABLE body (no ALTER TABLE ADD CONSTRAINT).
        $this->assertSame(
            <<<'SQL'
            CREATE TABLE "posts" (
              "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
              "user_id" int NOT NULL,
              CONSTRAINT "fk_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
            )
            SQL,
            $statements[0],
        );

        // Non-primary index is a separate CREATE INDEX statement.
        $this->assertSame(
            'CREATE INDEX "idx_user" ON "posts" ("user_id")',
            $statements[1],
        );
    }

    public function testCompileDropTable(): void
    {
        $this->assertSame(
            'DROP TABLE IF EXISTS "posts"',
            $this->dialect()->compileDropTable(new DropTable('posts', ifExists: true)),
        );
    }

    public function testCompileMigrateData(): void
    {
        $this->assertSame(
            'INSERT INTO "users_v2" ("id") SELECT "id" FROM "users"',
            $this->dialect()->compileMigrateData(new MigrateData('users', 'users_v2', ['id' => 'id'])),
        );
    }

    public function testCompileCreateViewOrReplaceEmitsDropThenCreate(): void
    {
        $view = new CreateView('active_users', 'SELECT * FROM users WHERE active = 1', orReplace: true);

        $this->assertSame(
            [
                'DROP VIEW IF EXISTS "active_users"',
                'CREATE VIEW "active_users" AS SELECT * FROM users WHERE active = 1',
            ],
            $this->toArray($this->dialect()->compileCreateView($view)),
        );
    }

    public function testCompileCreateTriggerWithWhen(): void
    {
        $trigger = new CreateTrigger(
            table: 'users',
            name: 'trg_update',
            timing: CreateTrigger::BEFORE,
            event: CreateTrigger::UPDATE,
            body: 'INSERT INTO audit (a) VALUES (1);',
            when: 'NEW.status != OLD.status',
        );

        $this->assertSame(
            'CREATE TRIGGER IF NOT EXISTS "trg_update" BEFORE UPDATE ON "users" FOR EACH ROW '
            . 'WHEN NEW.status != OLD.status BEGIN INSERT INTO audit (a) VALUES (1); END',
            $this->dialect()->compileCreateTrigger($trigger),
        );
    }

    public function testCompileAlterTableNativePathWithoutSchema(): void
    {
        // No rebuild-triggering op and no schema -> native ALTER statements.
        $alter = new AlterTable('users');
        $alter->addColumn('email', 'varchar(255)', nullable: true)
            ->renameColumn('fullname', 'display_name')
            ->addIndex('idx_email', ['email']);

        $this->assertSame(
            [
                'ALTER TABLE "users" ADD COLUMN "email" varchar(255) DEFAULT NULL',
                'ALTER TABLE "users" RENAME COLUMN "fullname" TO "display_name"',
                'CREATE INDEX "idx_email" ON "users" ("email")',
            ],
            $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext())),
        );
    }

    public function testModifyCharsetIsSilentlyIgnored(): void
    {
        $alter = new AlterTable('users');
        $alter->modifyCharset('utf8mb4');

        $this->assertSame(
            [],
            $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext())),
        );
    }
}
