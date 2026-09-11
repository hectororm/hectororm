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

use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\MySQLDialect;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\DropForeignKey;
use PHPUnit\Framework\TestCase;

/**
 * Class MySQLDialectTest.
 *
 * Unit tests targeting the MySQL dialect directly (not through the compiler).
 */
class MySQLDialectTest extends TestCase
{
    private function dialect(?MySQLCapabilities $capabilities = null): MySQLDialect
    {
        return new MySQLDialect($capabilities);
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

        $this->assertSame(['mysql', 'mariadb', 'vitess'], $dialect->driverNames());
        $this->assertTrue($dialect->supportsAlterForeignKey());
        $this->assertFalse($dialect->inlinesCreateTableForeignKeys());
    }

    public function testForeignKeyChecks(): void
    {
        $dialect = $this->dialect();

        $this->assertSame('SET FOREIGN_KEY_CHECKS = 0', $dialect->compileDisableForeignKeyChecks());
        $this->assertSame('SET FOREIGN_KEY_CHECKS = 1', $dialect->compileEnableForeignKeyChecks());
    }

    public function testCompileCreateTableWithBacktickQuotingAndOptions(): void
    {
        $createTable = new CreateTable('posts', charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci');
        $createTable->addColumn('id', 'int', autoIncrement: true)
            ->addColumn('title', 'varchar(255)')
            ->addIndex('PRIMARY', ['id'], Index::PRIMARY);

        $this->assertSame(
            [
                <<<'SQL'
                CREATE TABLE `posts` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `title` varchar(255) NOT NULL,
                  PRIMARY KEY (`id`)
                ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                SQL,
            ],
            $this->toArray($this->dialect()->compileCreateTable($createTable)),
        );
    }

    public function testCompileDropTable(): void
    {
        $this->assertSame(
            'DROP TABLE `posts`',
            $this->dialect()->compileDropTable(new DropTable('posts')),
        );
        $this->assertSame(
            'DROP TABLE IF EXISTS `posts`',
            $this->dialect()->compileDropTable(new DropTable('posts', ifExists: true)),
        );
    }

    public function testCompileDropView(): void
    {
        $this->assertSame(
            'DROP VIEW IF EXISTS `v`',
            $this->dialect()->compileDropView(new DropView('v', ifExists: true)),
        );
    }

    public function testCompileMigrateData(): void
    {
        $this->assertSame(
            'INSERT INTO `users_v2` SELECT * FROM `users`',
            $this->dialect()->compileMigrateData(new MigrateData('users', 'users_v2')),
        );
        $this->assertSame(
            'INSERT INTO `users_v2` (`id`, `full_name`) SELECT `id`, `name` FROM `users`',
            $this->dialect()->compileMigrateData(
                new MigrateData('users', 'users_v2', ['id' => 'id', 'name' => 'full_name']),
            ),
        );
    }

    public function testCompileAddForeignKeyUsesAlterTableAddConstraint(): void
    {
        $operation = new AddForeignKey(
            table: 'posts',
            name: 'fk_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: ForeignKey::RULE_CASCADE,
        );

        $this->assertSame(
            'ALTER TABLE `posts` ADD CONSTRAINT `fk_author` FOREIGN KEY (`author_id`) '
            . 'REFERENCES `users` (`id`) ON DELETE CASCADE',
            $this->dialect()->compileAddForeignKey($operation),
        );
    }

    public function testCompileDropForeignKeyUsesMysqlSyntax(): void
    {
        $this->assertSame(
            'ALTER TABLE `posts` DROP FOREIGN KEY `fk_author`',
            $this->dialect()->compileDropForeignKey(new DropForeignKey('posts', 'fk_author')),
        );
    }

    public function testCompileAlterTableCombinesClausesIntoSingleStatement(): void
    {
        $alter = new AlterTable('users');
        $alter->addColumn('email', 'varchar(255)', hasDefault: true, default: '')
            ->dropColumn('legacy')
            ->addIndex('idx_email', ['email'], Index::UNIQUE);

        $this->assertSame(
            [
                "ALTER TABLE `users` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '', "
                . 'DROP COLUMN `legacy`, ADD UNIQUE INDEX `idx_email` (`email`)',
            ],
            $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext())),
        );
    }

    public function testCompileAlterTableEmitsRenameAsSeparateStatement(): void
    {
        $alter = new AlterTable('users');
        $alter->addColumn('email', 'varchar(255)', nullable: true);
        $alter->renameTable('members');

        $this->assertSame(
            [
                'ALTER TABLE `users` ADD COLUMN `email` varchar(255) NULL DEFAULT NULL',
                'ALTER TABLE `users` RENAME TO `members`',
            ],
            $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext())),
        );
    }

    public function testCompileAlterTableThrowsOnNotNullColumnWithoutDefault(): void
    {
        $alter = new AlterTable('users');
        $alter->addColumn('avatar', 'varchar(255)');

        $this->expectException(PlanException::class);

        $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext()));
    }

    public function testRenameColumnUsesModernSyntaxWithoutCapabilities(): void
    {
        $alter = new AlterTable('users');
        $alter->renameColumn('name', 'display_name');

        $this->assertSame(
            ['ALTER TABLE `users` RENAME COLUMN `name` TO `display_name`'],
            $this->toArray($this->dialect()->compileAlterTable($alter, new CompilationContext())),
        );
    }

    public function testRenameColumnWithoutSchemaOnLegacyServerThrows(): void
    {
        $capabilities = new MySQLCapabilities(new DriverInfo('mysql', '5.7.44'));
        $dialect = $this->dialect($capabilities);

        $alter = new AlterTable('users');
        $alter->renameColumn('name', 'display_name');

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('does not support RENAME COLUMN');

        $this->toArray($dialect->compileAlterTable($alter, new CompilationContext()));
    }
}
