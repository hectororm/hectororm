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
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use Hector\Schema\Table;

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
                return 'ALTER TABLE `old_table` RENAME TO `new_table`';

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

            // RAW statements
            case 'rawSimple':
                return 'CREATE FULLTEXT INDEX ft_search ON articles (title, body)';

            case 'rawBetweenOperations':
                return [
                    // Structure: alter users, raw, alter posts (in declaration order)
                    "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT ''",
                    'CREATE FULLTEXT INDEX ft_email ON users (email)',
                    'ALTER TABLE `posts` DROP COLUMN `legacy`',
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
                    CREATE TABLE `users` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    'CREATE FULLTEXT INDEX ft_name ON users (name)',
                    <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `user_id` int NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    // Post pass: FK last (global)
                    'ALTER TABLE `posts` ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
                ];

            case 'rawOnly':
                return 'ALTER TABLE users ENGINE = InnoDB';

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

            // TRIGGER operations
            case 'createTrigger':
                return 'CREATE TRIGGER `trg_users_insert` AFTER INSERT ON `users` FOR EACH ROW INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\')';

            case 'createTriggerWithWhen':
                // MySQL ignores WHEN — body is emitted directly
                return 'CREATE TRIGGER `trg_users_update` BEFORE UPDATE ON `users` FOR EACH ROW INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'update\')';

            case 'dropTrigger':
                return 'DROP TRIGGER IF EXISTS `trg_users_insert`';

            case 'createTableWithTrigger':
                return [
                    <<<'SQL'
                    CREATE TABLE `users` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `name` varchar(255) NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    // Trigger emitted in Post pass
                    'CREATE TRIGGER `trg_users_insert` AFTER INSERT ON `users` FOR EACH ROW INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\')',
                ];

            case 'alterTableDropTrigger':
                // DropTrigger is Pre pass — emitted before structure
                return 'DROP TRIGGER IF EXISTS `trg_users_insert`';

            // RAW with driver filter
            case 'rawDriverMatch':
                // drivers: ['mysql'] — MySQLCompiler handles mysql, mariadb, vitess
                return 'ALTER TABLE users ENGINE = InnoDB';

            case 'rawDriverMatchMultiple':
                // drivers: ['mysql', 'mariadb'] — both in MySQLCompiler
                return 'ALTER TABLE users ENGINE = InnoDB';

            case 'rawDriverMismatch':
                // drivers: ['pgsql'] — not handled by MySQLCompiler
                return [];

            case 'rawDriverNull':
                // drivers: null — emitted for all drivers
                return 'SELECT 1';

            case 'rawMixedDrivers':
                // First: drivers: ['mysql'] — match. Second: drivers: ['sqlite'] — skip. Third: null — match.
                return [
                    'ALTER TABLE users ENGINE = InnoDB',
                    'SELECT 1',
                ];

            // FK CHECKS operations
            case 'disableFkChecks':
                return 'SET FOREIGN_KEY_CHECKS = 0';

            case 'enableFkChecks':
                return 'SET FOREIGN_KEY_CHECKS = 1';

            case 'fkChecksWrapping':
                // Pass 1: disable. Pass 2: create table. Pass 3: enable.
                return [
                    'SET FOREIGN_KEY_CHECKS = 0',
                    <<<'SQL'
                    CREATE TABLE `users` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    'SET FOREIGN_KEY_CHECKS = 1',
                ];

            case 'fkChecksWithForeignKey':
                // Pass 1: disable. Pass 2: create table (no FK inline). Pass 3: add FK + enable.
                return [
                    'SET FOREIGN_KEY_CHECKS = 0',
                    <<<'SQL'
                    CREATE TABLE `posts` (
                      `id` int NOT NULL AUTO_INCREMENT,
                      `user_id` int NOT NULL,
                      PRIMARY KEY (`id`)
                    )
                    SQL,
                    'ALTER TABLE `posts` ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
                    'SET FOREIGN_KEY_CHECKS = 1',
                ];

            case 'fkChecksWithTriggerAndFk':
                // Pass 1: disable + drop FK. Pass 2: alter structure. Pass 3: add FK + trigger + enable.
                return [
                    'SET FOREIGN_KEY_CHECKS = 0',
                    'ALTER TABLE `posts` DROP FOREIGN KEY `fk_old`',
                    'ALTER TABLE `posts` ADD COLUMN `category_id` int NULL DEFAULT NULL',
                    'ALTER TABLE `posts` ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)',
                    'CREATE TRIGGER `trg_audit` AFTER INSERT ON `posts` FOR EACH ROW INSERT INTO audit_log (action) VALUES (\'insert\')',
                    'SET FOREIGN_KEY_CHECKS = 1',
                ];

            // ALTER TABLE — modify charset
            case 'alterModifyCharset':
                return 'ALTER TABLE `users` DEFAULT CHARSET=utf8mb4';

            case 'alterModifyCharsetWithCollation':
                return 'ALTER TABLE `users` DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

            default:
                throw new Exception(sprintf('Scenario "%s" not implemented', $scenario));
        }
    }

    /**
     * Without capabilities (default), RENAME COLUMN is emitted (modern syntax).
     */
    public function testRenameColumnWithoutCapabilities(): void
    {
        $plan = new Plan();
        $plan->alter('users')->renameColumn('name', 'display_name');

        $compiler = new MySQLCompiler();
        $statements = iterator_to_array($plan->getStatements($compiler), false);

        $this->assertSame(
            ['ALTER TABLE `users` RENAME COLUMN `name` TO `display_name`'],
            $statements,
        );
    }

    /**
     * With MySQL >= 8.0 capabilities, RENAME COLUMN is emitted.
     */
    public function testRenameColumnWithMySQL8Capabilities(): void
    {
        $capabilities = new MySQLCapabilities(new DriverInfo('mysql', '8.0.35'));

        $plan = new Plan();
        $plan->alter('users')->renameColumn('name', 'display_name');

        $compiler = new MySQLCompiler($capabilities);
        $statements = iterator_to_array($plan->getStatements($compiler), false);

        $this->assertSame(
            ['ALTER TABLE `users` RENAME COLUMN `name` TO `display_name`'],
            $statements,
        );
    }

    /**
     * With MySQL 5.7 capabilities and a schema, CHANGE COLUMN is emitted.
     */
    public function testRenameColumnWithMySQL57FallsBackToChangeColumn(): void
    {
        $capabilities = new MySQLCapabilities(new DriverInfo('mysql', '5.7.44'));

        $table = new Table(
            schema_name: 'mydb',
            type: Table::TYPE_TABLE,
            name: 'users',
            columns: [
                'id' => new Column(name: 'id', position: 0, default: null, nullable: false, type: 'int',
                    auto_increment: true),
                'name' => new Column(name: 'name', position: 1, default: null, nullable: false, type: 'varchar',
                    maxlength: 100),
            ],
            indexes: [
                'PRIMARY' => new Index(name: 'PRIMARY', type: Index::PRIMARY, columns_name: ['id']),
            ],
        );

        $schema = new Schema(
            connection: 'default',
            name: 'mydb',
            charset: 'utf8mb4',
            tables: ['users' => $table],
        );

        $plan = new Plan();
        $plan->alter('users')->renameColumn('name', 'display_name');

        $compiler = new MySQLCompiler($capabilities);
        $statements = iterator_to_array($plan->getStatements($compiler, $schema), false);

        $this->assertSame(
            ['ALTER TABLE `users` CHANGE COLUMN `name` `display_name` varchar(100) NOT NULL'],
            $statements,
        );
    }

    /**
     * With MySQL 5.7 capabilities, nullable column with default is correctly reproduced.
     */
    public function testRenameColumnChangeColumnPreservesNullableAndDefault(): void
    {
        $capabilities = new MySQLCapabilities(new DriverInfo('mysql', '5.7.44'));

        $table = new Table(
            schema_name: 'mydb',
            type: Table::TYPE_TABLE,
            name: 'users',
            columns: [
                'id' => new Column(name: 'id', position: 0, default: null, nullable: false, type: 'int',
                    auto_increment: true),
                'bio' => new Column(name: 'bio', position: 1, default: 'N/A', nullable: true, type: 'text'),
            ],
            indexes: [
                'PRIMARY' => new Index(name: 'PRIMARY', type: Index::PRIMARY, columns_name: ['id']),
            ],
        );

        $schema = new Schema(
            connection: 'default',
            name: 'mydb',
            charset: 'utf8mb4',
            tables: ['users' => $table],
        );

        $plan = new Plan();
        $plan->alter('users')->renameColumn('bio', 'biography');

        $compiler = new MySQLCompiler($capabilities);
        $statements = iterator_to_array($plan->getStatements($compiler, $schema), false);

        $this->assertSame(
            ["ALTER TABLE `users` CHANGE COLUMN `bio` `biography` text NULL DEFAULT 'N/A'"],
            $statements,
        );
    }

    /**
     * With MySQL 5.7 capabilities but no schema, a PlanException is thrown.
     */
    public function testRenameColumnWithMySQL57WithoutSchemaThrowsException(): void
    {
        $capabilities = new MySQLCapabilities(new DriverInfo('mysql', '5.7.44'));

        $plan = new Plan();
        $plan->alter('users')->renameColumn('name', 'display_name');

        $compiler = new MySQLCompiler($capabilities);

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('does not support RENAME COLUMN');

        iterator_to_array($plan->getStatements($compiler), false);
    }
}
