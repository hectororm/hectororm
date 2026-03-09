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
use Hector\Schema\Plan\Compiler\MySQLCompiler;

/**
 * Class MySQLCompilerTest.
 */
class MySQLCompilerTest extends AbstractCompilerTestCase
{
    protected function getCompiler(): CompilerInterface
    {
        return new MySQLCompiler();
    }

    protected function expected(string $scenario): array|string|null
    {
        switch ($scenario) {
            // CREATE TABLE
            case 'createTableSimple':
                return <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `title` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL;

            case 'createTableWithOptions':
                return <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `title` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`)
                    ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    SQL;

            case 'createTableWithForeignKey':
                return [
                    <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `user_id` int NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    'ALTER TABLE `posts` ADD CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
                ];

            case 'createTableMultipleIndexes':
                return <<<'SQL'
                    CREATE TABLE `users` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `email` varchar(255) NOT NULL,
                      `name` varchar(100) NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE INDEX `idx_email` (`email`),
                      INDEX `idx_name` (`name`)
                    )
                    SQL;

            case 'createTableIfNotExists':
                return <<<'SQL'
                    CREATE TABLE IF NOT EXISTS `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `title` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL;

            case 'createTableWithoutAutoIncrement':
                return <<<'SQL'
                    CREATE TABLE `categories` (
                      `id` INTEGER NOT NULL,
                      `name` TEXT NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL;

            // DROP / RENAME TABLE
            case 'dropTable':
                return 'DROP TABLE `posts`';

            case 'dropTableIfExists':
                return 'DROP TABLE IF EXISTS `posts`';

            case 'renameTable':
                return 'RENAME TABLE `old_table` TO `new_table`';

            // ALTER TABLE — columns
            case 'alterAddColumns':
                return "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '', ADD COLUMN `phone` varchar(20) NULL DEFAULT NULL";

            case 'alterDropColumn':
                return 'ALTER TABLE `users` DROP COLUMN `old_field`';

            case 'alterModifyColumn':
                return 'ALTER TABLE `users` MODIFY COLUMN `name` varchar(500) NOT NULL';

            case 'alterModifyColumnWithAfter':
                return 'ALTER TABLE `users` MODIFY COLUMN `email` varchar(500) NOT NULL AFTER `name`';

            case 'alterRenameColumn':
                return 'ALTER TABLE `users` RENAME COLUMN `fullname` TO `display_name`';

            // ALTER TABLE — indexes
            case 'alterAddIndex':
                return 'ALTER TABLE `users` ADD INDEX `idx_name` (`name`)';

            case 'alterAddUniqueIndex':
                return 'ALTER TABLE `users` ADD UNIQUE INDEX `idx_email` (`email`)';

            case 'alterDropIndex':
                return 'ALTER TABLE `users` DROP INDEX `idx_old`';

            // ALTER TABLE — foreign keys
            case 'alterAddForeignKey':
                return 'ALTER TABLE `posts` ADD CONSTRAINT `fk_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE';

            case 'alterDropForeignKey':
                return 'ALTER TABLE `posts` DROP FOREIGN KEY `fk_author`';

            // ALTER TABLE — mixed
            case 'alterMixedOperations':
                return "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '', DROP COLUMN `legacy`, RENAME COLUMN `fullname` TO `display_name`, ADD UNIQUE INDEX `idx_email` (`email`)";

            // ALTER TABLE — mixed with rebuild-triggering operation (MySQL handles natively)
            case 'alterMixedWithRebuild':
                return 'ALTER TABLE `users` ADD COLUMN `avatar` varchar(255) NULL DEFAULT NULL, DROP COLUMN `legacy`, MODIFY COLUMN `name` TEXT NOT NULL, ADD COLUMN `bio` text NULL DEFAULT NULL, DROP COLUMN `temp`';

            // Column options
            case 'columnWithDefault':
                return "ALTER TABLE `users` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'active'";

            case 'columnDefaultBoolean':
                return 'ALTER TABLE `users` ADD COLUMN `active` tinyint(1) NOT NULL DEFAULT 1';

            case 'columnDefaultInteger':
                return 'ALTER TABLE `users` ADD COLUMN `score` int NOT NULL DEFAULT 0';

            case 'columnNullable':
                return 'ALTER TABLE `users` ADD COLUMN `bio` text NULL DEFAULT NULL';

            case 'columnNullableWithNullDefault':
                return 'ALTER TABLE `users` ADD COLUMN `deleted_at` datetime NULL DEFAULT NULL';

            case 'columnAutoIncrement':
                return 'ALTER TABLE `users` ADD COLUMN `id` int NOT NULL AUTO_INCREMENT';

            case 'columnAfter':
                return "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '' AFTER `name`";

            case 'columnFirst':
                return 'ALTER TABLE `users` ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST';

            // Edge cases
            case 'emptyPlan':
            case 'emptyTablePlan':
                return [];

            case 'multipleTablePlans':
                return [
                    "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT ''",
                    'ALTER TABLE `posts` DROP COLUMN `legacy`',
                ];

            // FK ordering
            case 'fkOrderingCreateTables':
                return [
                    // Structure first (both CREATE TABLEs)
                    <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `user_id` int NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    <<<'SQL'
                    CREATE TABLE `users` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `name` varchar(100) NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    // FK last
                    'ALTER TABLE `posts` ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
                ];

            case 'fkOrderingDropBeforeStructure':
                return [
                    // Drop FK first
                    'ALTER TABLE `posts` DROP FOREIGN KEY `fk_user`',
                    // Then structure
                    'ALTER TABLE `posts` DROP COLUMN `user_id`',
                ];

            // Validation
            case 'alterAddColumnNotNullWithoutDefault':
                $this->expectException(PlanException::class);
                return null;

            // MIGRATE DATA
            case 'migrateAllColumns':
                return 'INSERT INTO `users_v2` SELECT * FROM `users`';

            case 'migrateWithMapping':
                return 'INSERT INTO `users_v2` (`id`, `full_name`) SELECT `id`, `name` FROM `users`';

            // VIEW operations
            case 'createView':
                return 'CREATE VIEW `active_users` AS SELECT * FROM users WHERE active = 1';

            case 'createViewOrReplace':
                return 'CREATE OR REPLACE VIEW `active_users` AS SELECT * FROM users WHERE active = 1';

            case 'createViewWithAlgorithm':
                return 'CREATE ALGORITHM = MERGE VIEW `active_users` AS SELECT * FROM users WHERE active = 1';

            case 'dropView':
                return 'DROP VIEW `old_view`';

            case 'dropViewIfExists':
                return 'DROP VIEW IF EXISTS `old_view`';

            case 'alterView':
                return 'ALTER VIEW `my_view` AS SELECT id, name FROM users';

            case 'alterViewWithAlgorithm':
                return 'ALTER ALGORITHM = TEMPTABLE VIEW `my_view` AS SELECT id, name FROM users';

            default:
                throw new \Exception(sprintf('Scenario "%s" not implemented', $scenario));
        }
    }
}
