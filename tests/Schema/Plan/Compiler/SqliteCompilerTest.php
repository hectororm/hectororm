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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Compiler\SqliteCompiler;

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

            default:
                throw new \Exception(sprintf('Scenario "%s" not implemented', $scenario));
        }
    }
}
