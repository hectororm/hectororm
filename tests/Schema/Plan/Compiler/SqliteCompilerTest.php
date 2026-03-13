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

use Exception;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use Hector\Schema\Table;

/**
 * Class SqliteCompilerTest.
 */
class SqliteCompilerTest extends AbstractCompilerTestCase
{
    protected function getCompiler(): CompilerInterface
    {
        return new SqliteCompiler();
    }

    protected function expected(string $scenario): array|string|null
    {
        switch ($scenario) {
            // CREATE TABLE
            case 'createTableSimple':
                return <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "title" varchar(255) NOT NULL
                    )
                    SQL;

            case 'createTableWithOptions':
                // SQLite ignores charset/collation
                return <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "title" varchar(255) NOT NULL
                    )
                    SQL;

            case 'createTableWithForeignKey':
                return [
                    <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "user_id" int NOT NULL
                    )
                    SQL,
                    'ALTER TABLE "posts" ADD CONSTRAINT "fk_posts_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE',
                ];

            case 'createTableMultipleIndexes':
                return [
                    "CREATE TABLE \"users\" (\n" .
                    "  \"id\" int NOT NULL PRIMARY KEY AUTOINCREMENT,\n" .
                    "  \"email\" varchar(255) NOT NULL,\n" .
                    "  \"name\" varchar(100) NOT NULL\n" .
                    ")",
                    'CREATE UNIQUE INDEX "idx_email" ON "users" ("email")',
                    'CREATE INDEX "idx_name" ON "users" ("name")',
                ];

            case 'createTableIfNotExists':
                return <<<'SQL'
                    CREATE TABLE IF NOT EXISTS "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "title" varchar(255) NOT NULL
                    )
                    SQL;

            case 'createTableWithoutAutoIncrement':
                return <<<'SQL'
                    CREATE TABLE "categories" (
                      "id" INTEGER NOT NULL,
                      "name" TEXT NOT NULL,
                      PRIMARY KEY ("id")
                    )
                    SQL;

            // DROP / RENAME TABLE
            case 'dropTable':
                return 'DROP TABLE "posts"';

            case 'dropTableIfExists':
                return 'DROP TABLE IF EXISTS "posts"';

            case 'renameTable':
                return 'ALTER TABLE "old_table" RENAME TO "new_table"';

            // ALTER TABLE — columns
            case 'alterAddColumns':
                return [
                    "ALTER TABLE \"users\" ADD COLUMN \"email\" varchar(255) NOT NULL DEFAULT ''",
                    'ALTER TABLE "users" ADD COLUMN "phone" varchar(20) DEFAULT NULL',
                ];

            case 'alterDropColumn':
                return 'ALTER TABLE "users" DROP COLUMN "old_field"';

            case 'alterModifyColumn':
                return 'ALTER TABLE "users" MODIFY COLUMN "name" varchar(500) NOT NULL';

            case 'alterModifyColumnWithAfter':
                return 'ALTER TABLE "users" MODIFY COLUMN "email" varchar(500) NOT NULL';

            case 'alterRenameColumn':
                return 'ALTER TABLE "users" RENAME COLUMN "fullname" TO "display_name"';

            // ALTER TABLE — indexes
            case 'alterAddIndex':
                return 'CREATE INDEX "idx_name" ON "users" ("name")';

            case 'alterAddUniqueIndex':
                return 'CREATE UNIQUE INDEX "idx_email" ON "users" ("email")';

            case 'alterDropIndex':
                return 'DROP INDEX IF EXISTS "idx_old"';

            // ALTER TABLE — foreign keys (naive SQL without schema)
            case 'alterAddForeignKey':
                return 'ALTER TABLE "posts" ADD CONSTRAINT "fk_author" FOREIGN KEY ("author_id") REFERENCES "users" ("id") ON DELETE CASCADE';

            case 'alterDropForeignKey':
                return 'ALTER TABLE "posts" DROP FOREIGN KEY "fk_author"';

            // ALTER TABLE — mixed
            case 'alterMixedOperations':
                return [
                    "ALTER TABLE \"users\" ADD COLUMN \"email\" varchar(255) NOT NULL DEFAULT ''",
                    'ALTER TABLE "users" DROP COLUMN "legacy"',
                    'ALTER TABLE "users" RENAME COLUMN "fullname" TO "display_name"',
                    'CREATE UNIQUE INDEX "idx_email" ON "users" ("email")',
                ];

            // ALTER TABLE — mixed with rebuild-triggering operation (naive SQL without schema)
            case 'alterMixedWithRebuild':
                return [
                    'ALTER TABLE "users" ADD COLUMN "avatar" varchar(255) DEFAULT NULL',
                    'ALTER TABLE "users" DROP COLUMN "legacy"',
                    'ALTER TABLE "users" MODIFY COLUMN "name" TEXT NOT NULL',
                    'ALTER TABLE "users" ADD COLUMN "bio" text DEFAULT NULL',
                    'ALTER TABLE "users" DROP COLUMN "temp"',
                ];

            // Column options
            case 'columnWithDefault':
                return "ALTER TABLE \"users\" ADD COLUMN \"status\" varchar(20) NOT NULL DEFAULT 'active'";

            case 'columnDefaultBoolean':
                return 'ALTER TABLE "users" ADD COLUMN "active" tinyint(1) NOT NULL DEFAULT 1';

            case 'columnDefaultInteger':
                return 'ALTER TABLE "users" ADD COLUMN "score" int NOT NULL DEFAULT 0';

            case 'columnNullable':
                return 'ALTER TABLE "users" ADD COLUMN "bio" text DEFAULT NULL';

            case 'columnNullableWithNullDefault':
                return 'ALTER TABLE "users" ADD COLUMN "deleted_at" datetime DEFAULT NULL';

            case 'columnAutoIncrement':
                return 'ALTER TABLE "users" ADD COLUMN "id" int NOT NULL PRIMARY KEY AUTOINCREMENT';

            case 'columnAfter':
                // SQLite ignores AFTER
                return "ALTER TABLE \"users\" ADD COLUMN \"email\" varchar(255) NOT NULL DEFAULT ''";

            case 'columnFirst':
                // SQLite ignores FIRST
                return 'ALTER TABLE "users" ADD COLUMN "id" int NOT NULL PRIMARY KEY AUTOINCREMENT';

            // Edge cases
            case 'emptyPlan':
            case 'emptyTablePlan':
                return [];

            case 'multipleTablePlans':
                return [
                    "ALTER TABLE \"users\" ADD COLUMN \"email\" varchar(255) NOT NULL DEFAULT ''",
                    'ALTER TABLE "posts" DROP COLUMN "legacy"',
                ];

            // FK ordering
            case 'fkOrderingCreateTables':
                return [
                    // Structure first (both CREATE TABLEs)
                    <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "user_id" int NOT NULL
                    )
                    SQL,
                    <<<'SQL'
                    CREATE TABLE "users" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "name" varchar(100) NOT NULL
                    )
                    SQL,
                    // FK last
                    'ALTER TABLE "posts" ADD CONSTRAINT "fk_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id")',
                ];

            case 'fkOrderingDropBeforeStructure':
                return [
                    // Drop FK first
                    'ALTER TABLE "posts" DROP FOREIGN KEY "fk_user"',
                    // Then structure
                    'ALTER TABLE "posts" DROP COLUMN "user_id"',
                ];

            // Validation
            case 'alterAddColumnNotNullWithoutDefault':
                $this->expectException(PlanException::class);
                return null;

            // MIGRATE DATA
            case 'migrateAllColumns':
                return 'INSERT INTO "users_v2" SELECT * FROM "users"';

            case 'migrateWithMapping':
                return 'INSERT INTO "users_v2" ("id", "full_name") SELECT "id", "name" FROM "users"';

            // RAW statements
            case 'rawSimple':
                return 'CREATE FULLTEXT INDEX ft_search ON articles (title, body)';

            case 'rawBetweenOperations':
                return [
                    // Structure: alter users, raw, alter posts (in declaration order)
                    "ALTER TABLE \"users\" ADD COLUMN \"email\" varchar(255) NOT NULL DEFAULT ''",
                    'CREATE FULLTEXT INDEX ft_email ON users (email)',
                    'ALTER TABLE "posts" DROP COLUMN "legacy"',
                ];

            case 'rawMultiple':
                return [
                    'SET @OLD_SQL_MODE=@@SQL_MODE',
                    'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"',
                ];

            case 'rawWithFkOrdering':
                return [
                    // Structure pass: CREATE users, raw, CREATE posts (in order)
                    <<<'SQL'
                    CREATE TABLE "users" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT
                    )
                    SQL,
                    'CREATE FULLTEXT INDEX ft_name ON users (name)',
                    <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "user_id" int NOT NULL
                    )
                    SQL,
                    // Post pass: FK last (global)
                    'ALTER TABLE "posts" ADD CONSTRAINT "fk_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id")',
                ];

            case 'rawOnly':
                return 'ALTER TABLE users ENGINE = InnoDB';

            // VIEW operations
            case 'createView':
                return 'CREATE VIEW "active_users" AS SELECT * FROM users WHERE active = 1';

            case 'createViewOrReplace':
                // SQLite does not support OR REPLACE, so DROP + CREATE
                return [
                    'DROP VIEW IF EXISTS "active_users"',
                    'CREATE VIEW "active_users" AS SELECT * FROM users WHERE active = 1',
                ];

            case 'createViewWithAlgorithm':
                // SQLite ignores algorithm
                return 'CREATE VIEW "active_users" AS SELECT * FROM users WHERE active = 1';

            case 'dropView':
                return 'DROP VIEW "old_view"';

            case 'dropViewIfExists':
                return 'DROP VIEW IF EXISTS "old_view"';

            case 'alterView':
                // SQLite does not support ALTER VIEW, so DROP + CREATE
                return [
                    'DROP VIEW IF EXISTS "my_view"',
                    'CREATE VIEW "my_view" AS SELECT id, name FROM users',
                ];

            case 'alterViewWithAlgorithm':
                // SQLite ignores algorithm, DROP + CREATE
                return [
                    'DROP VIEW IF EXISTS "my_view"',
                    'CREATE VIEW "my_view" AS SELECT id, name FROM users',
                ];

            // TRIGGER operations
            case 'createTrigger':
                return 'CREATE TRIGGER IF NOT EXISTS "trg_users_insert" AFTER INSERT ON "users" FOR EACH ROW BEGIN INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\'); END';

            case 'createTriggerWithWhen':
                return 'CREATE TRIGGER IF NOT EXISTS "trg_users_update" BEFORE UPDATE ON "users" FOR EACH ROW WHEN NEW.status != OLD.status BEGIN INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'update\'); END';

            case 'dropTrigger':
                return 'DROP TRIGGER IF EXISTS "trg_users_insert"';

            case 'createTableWithTrigger':
                return [
                    <<<'SQL'
                    CREATE TABLE "users" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "name" varchar(255) NOT NULL
                    )
                    SQL,
                    // Trigger emitted in Post pass
                    'CREATE TRIGGER IF NOT EXISTS "trg_users_insert" AFTER INSERT ON "users" FOR EACH ROW BEGIN INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\'); END',
                ];

            case 'alterTableDropTrigger':
                // DropTrigger is Pre pass — emitted before structure
                return 'DROP TRIGGER IF EXISTS "trg_users_insert"';

            // RAW with driver filter
            case 'rawDriverMatch':
                // drivers: ['mysql'] — not handled by SqliteCompiler
                return [];

            case 'rawDriverMatchMultiple':
                // drivers: ['mysql', 'mariadb'] — not handled by SqliteCompiler
                return [];

            case 'rawDriverMismatch':
                // drivers: ['pgsql'] — not handled by SqliteCompiler
                return [];

            case 'rawDriverNull':
                // drivers: null — emitted for all drivers
                return 'SELECT 1';

            case 'rawMixedDrivers':
                // First: drivers: ['mysql'] — skip. Second: drivers: ['sqlite'] — match. Third: null — match.
                return [
                    'PRAGMA journal_mode = WAL',
                    'SELECT 1',
                ];

            // FK CHECKS operations
            case 'disableFkChecks':
                return 'PRAGMA foreign_keys = OFF';

            case 'enableFkChecks':
                return 'PRAGMA foreign_keys = ON';

            case 'fkChecksWrapping':
                // Pass 1: disable. Pass 2: create table. Pass 3: enable.
                return [
                    'PRAGMA foreign_keys = OFF',
                    <<<'SQL'
                    CREATE TABLE "users" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT
                    )
                    SQL,
                    'PRAGMA foreign_keys = ON',
                ];

            case 'fkChecksWithForeignKey':
                // Pass 1: disable. Pass 2: create table (no FK inline). Pass 3: add FK + enable.
                return [
                    'PRAGMA foreign_keys = OFF',
                    <<<'SQL'
                    CREATE TABLE "posts" (
                      "id" int NOT NULL PRIMARY KEY AUTOINCREMENT,
                      "user_id" int NOT NULL
                    )
                    SQL,
                    'ALTER TABLE "posts" ADD CONSTRAINT "fk_user" FOREIGN KEY ("user_id") REFERENCES "users" ("id")',
                    'PRAGMA foreign_keys = ON',
                ];

            case 'fkChecksWithTriggerAndFk':
                // Pass 1: disable + drop FK. Pass 2: alter structure. Pass 3: add FK + trigger + enable.
                return [
                    'PRAGMA foreign_keys = OFF',
                    'ALTER TABLE "posts" DROP FOREIGN KEY "fk_old"',
                    'ALTER TABLE "posts" ADD COLUMN "category_id" int DEFAULT NULL',
                    'ALTER TABLE "posts" ADD CONSTRAINT "fk_category" FOREIGN KEY ("category_id") REFERENCES "categories" ("id")',
                    'CREATE TRIGGER IF NOT EXISTS "trg_audit" AFTER INSERT ON "posts" FOR EACH ROW BEGIN INSERT INTO audit_log (action) VALUES (\'insert\'); END',
                    'PRAGMA foreign_keys = ON',
                ];

            // ALTER TABLE — modify charset (silently ignored on SQLite)
            case 'alterModifyCharset':
            case 'alterModifyCharsetWithCollation':
                return [];

            default:
                throw new Exception(sprintf('Scenario "%s" not implemented', $scenario));
        }
    }

    /**
     * When the plan contains DisableForeignKeyChecks, the SQLite table rebuild
     * must NOT emit its own PRAGMA foreign_keys = OFF/ON, because the plan
     * already manages FK checks globally.
     */
    public function testRebuildSkipsPragmaWhenFkChecksManaged(): void
    {
        // Build a schema with a "posts" table that has columns, an index and a FK
        $table = new Table(
            schema_name: 'mydb',
            type: Table::TYPE_TABLE,
            name: 'posts',
            columns: [
                'id' => new Column(name: 'id', position: 0, default: null, nullable: false, type: 'int',
                    auto_increment: true),
                'title' => new Column(name: 'title', position: 1, default: null, nullable: false, type: 'varchar',
                    maxlength: 255),
                'user_id' => new Column(name: 'user_id', position: 2, default: null, nullable: false, type: 'int'),
            ],
            indexes: [
                'PRIMARY' => new Index(name: 'PRIMARY', type: Index::PRIMARY, columns_name: ['id']),
            ],
            foreign_keys: [
                'fk_user' => new ForeignKey(
                    name: 'fk_user',
                    columns_name: ['user_id'],
                    referenced_schema_name: 'mydb',
                    referenced_table_name: 'users',
                    referenced_columns_name: ['id'],
                ),
            ],
        );

        $schema = new Schema(
            connection: 'default',
            name: 'mydb',
            charset: 'utf8mb4',
            tables: ['posts' => $table],
        );

        // Plan: disable FK checks, alter with a rebuild-triggering operation (drop FK), enable FK checks
        $plan = new Plan();
        $plan->add(new DisableForeignKeyChecks());
        $plan->alter('posts', function ($t) {
            $t->dropForeignKey('fk_user');
            $t->addColumn('category_id', 'int', nullable: true);
        });
        $plan->add(new EnableForeignKeyChecks());

        $compiler = new SqliteCompiler();
        $statements = iterator_to_array($plan->getStatements($compiler, $schema), false);

        // The PRAGMA foreign_keys = OFF/ON should appear exactly once each
        // (from the plan-level FK check operations, NOT from the rebuild)
        $pragmaOff = array_filter($statements, fn(string $s) => 'PRAGMA foreign_keys = OFF' === $s);
        $pragmaOn = array_filter($statements, fn(string $s) => 'PRAGMA foreign_keys = ON' === $s);

        $this->assertCount(1, $pragmaOff, 'PRAGMA foreign_keys = OFF should appear exactly once');
        $this->assertCount(1, $pragmaOn, 'PRAGMA foreign_keys = ON should appear exactly once');

        // PRAGMA OFF must be first, PRAGMA ON must be last
        $this->assertSame('PRAGMA foreign_keys = OFF', $statements[0]);
        $this->assertSame('PRAGMA foreign_keys = ON', $statements[count($statements) - 1]);
    }

    /**
     * Without DisableForeignKeyChecks, the rebuild should still emit its own PRAGMA.
     */
    public function testRebuildEmitsPragmaWithoutFkChecksManaged(): void
    {
        $table = new Table(
            schema_name: 'mydb',
            type: Table::TYPE_TABLE,
            name: 'posts',
            columns: [
                'id' => new Column(name: 'id', position: 0, default: null, nullable: false, type: 'int',
                    auto_increment: true),
                'title' => new Column(name: 'title', position: 1, default: null, nullable: false, type: 'varchar',
                    maxlength: 255),
                'user_id' => new Column(name: 'user_id', position: 2, default: null, nullable: false, type: 'int'),
            ],
            indexes: [
                'PRIMARY' => new Index(name: 'PRIMARY', type: Index::PRIMARY, columns_name: ['id']),
            ],
            foreign_keys: [
                'fk_user' => new ForeignKey(
                    name: 'fk_user',
                    columns_name: ['user_id'],
                    referenced_schema_name: 'mydb',
                    referenced_table_name: 'users',
                    referenced_columns_name: ['id'],
                ),
            ],
        );

        $schema = new Schema(
            connection: 'default',
            name: 'mydb',
            charset: 'utf8mb4',
            tables: ['posts' => $table],
        );

        // Plan: alter with a rebuild-triggering operation (drop FK), NO explicit FK check operations
        $plan = new Plan();
        $plan->alter('posts', function ($t) {
            $t->dropForeignKey('fk_user');
            $t->addColumn('category_id', 'int', nullable: true);
        });

        $compiler = new SqliteCompiler();
        $statements = iterator_to_array($plan->getStatements($compiler, $schema), false);

        // The rebuild PRAGMA should still be present
        $pragmaOff = array_filter($statements, fn(string $s) => 'PRAGMA foreign_keys = OFF' === $s);
        $pragmaOn = array_filter($statements, fn(string $s) => 'PRAGMA foreign_keys = ON' === $s);

        $this->assertCount(1, $pragmaOff, 'Rebuild should emit PRAGMA foreign_keys = OFF');
        $this->assertCount(1, $pragmaOn, 'Rebuild should emit PRAGMA foreign_keys = ON');

        // PRAGMA OFF is emitted inside the rebuild (after pre-operations like DROP FK)
        $this->assertContains('PRAGMA foreign_keys = OFF', $statements);
    }
}
