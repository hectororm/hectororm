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

namespace Hector\Schema\Tests\Plan\Compiler\Dialect\Sqlite;

use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\TableRebuilder;
use Hector\Schema\Plan\Compiler\Dialect\SqliteDialect;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

/**
 * Class TableRebuilderTest.
 */
class TableRebuilderTest extends TestCase
{
    public function testStoredAddRequiresRebuildButVirtualAddDoesNot(): void
    {
        $virtual = new AlterTable('users');
        $virtual->addColumn('computed', 'INTEGER', generated: 'length(name)');
        $stored = new AlterTable('users');
        $stored->addColumn('computed', 'INTEGER', generated: new Generated('length(name)', stored: true));

        $this->assertFalse($this->rebuilder()->isRequired($virtual));
        $this->assertTrue($this->rebuilder()->isRequired($stored));
    }

    public function testRebuildWithStoredAddEmitsGeneratedDefinitionAndWritableMapping(): void
    {
        $alter = new AlterTable('users');
        $alter->addColumn('computed', 'INTEGER', generated: new Generated('length(name)', stored: true));
        $statements = [...(new SqliteDialect())->compileAlterTable($alter, new CompilationContext($this->schemaWithUsers()))];

        $this->assertStringContainsString(
            '"computed" INTEGER GENERATED ALWAYS AS (length(name)) STORED NOT NULL',
            $statements[1],
        );
        $this->assertStringContainsString('("id", "name", "legacy") SELECT "id", "name", "legacy"', $statements[2]);
        $this->assertStringNotContainsString('computed', $statements[2]);
    }

    public function testRebuilderRequiresSchema(): void
    {
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('SQLite table rebuild requires an existing schema');
        $this->rebuilder()->compile(new AlterTable('users'), new CompilationContext());
    }

    private function rebuilder(): TableRebuilder
    {
        // Callables are irrelevant for isRequired(); provide trivial stubs.
        return new TableRebuilder(
            createIndexCompiler: fn(string $table, AddIndex $index): string => '',
            planCompiler: fn($plan, CompilationContext $context): iterable => [],
        );
    }

    /**
     * Build a schema with a single "users" table for rebuild tests.
     */
    private function schemaWithUsers(): Schema
    {
        $table = new Table(
            schema_name: 'main',
            type: Table::TYPE_TABLE,
            name: 'users',
            columns: [
                'id' => new Column(name: 'id', position: 0, default: null, nullable: false, type: 'INTEGER',
                    auto_increment: true),
                'name' => new Column(name: 'name', position: 1, default: null, nullable: false, type: 'varchar',
                    maxlength: 100),
                'legacy' => new Column(name: 'legacy', position: 2, default: null, nullable: true, type: 'text'),
            ],
            indexes: [
                'PRIMARY' => new Index(name: 'PRIMARY', type: Index::PRIMARY, columns_name: ['id']),
                'idx_name' => new Index(name: 'idx_name', type: Index::INDEX, columns_name: ['name']),
            ],
        );

        return new Schema(connection: 'default', name: 'main', charset: 'utf8mb4', tables: ['users' => $table]);
    }

    public function testIsRequiredForRebuildTriggeringOperations(): void
    {
        $rebuilder = $this->rebuilder();

        $modify = new AlterTable('users');
        $modify->modifyColumn('name', 'TEXT');
        $this->assertTrue($rebuilder->isRequired($modify));

        $drop = new AlterTable('users');
        $drop->dropColumn('legacy');
        $this->assertTrue($rebuilder->isRequired($drop));

        $addFk = new AlterTable('users');
        $addFk->addForeignKey('fk', ['x'], 'ref', ['id']);
        $this->assertTrue($rebuilder->isRequired($addFk));

        $dropFk = new AlterTable('users');
        $dropFk->dropForeignKey('fk');
        $this->assertTrue($rebuilder->isRequired($dropFk));
    }

    public function testIsNotRequiredForNativelySupportedOperations(): void
    {
        $rebuilder = $this->rebuilder();

        $alter = new AlterTable('users');
        $alter->addColumn('email', 'varchar(255)', nullable: true)
            ->renameColumn('name', 'display_name')
            ->addIndex('idx_email', ['email'])
            ->dropIndex('idx_old');

        $this->assertFalse($rebuilder->isRequired($alter));
    }

    /**
     * Full rebuild sequence, driven through SqliteDialect (which wires the
     * rebuilder with the real Compiler + CREATE INDEX callables).
     */
    public function testRebuildSequenceForDropColumn(): void
    {
        $dialect = new SqliteDialect();
        $context = new CompilationContext($this->schemaWithUsers(), false);

        $alter = new AlterTable('users');
        $alter->dropColumn('legacy');

        $statements = iterator_to_array($dialect->compileAlterTable($alter, $context), false);

        // 1. PRAGMA off (FK checks not managed by the plan)
        $this->assertSame('PRAGMA foreign_keys = OFF', $statements[0]);

        // 2. CREATE TABLE temp (without the dropped "legacy" column, PK inline)
        $this->assertStringStartsWith('CREATE TABLE "__htemp_', $statements[1]);
        $this->assertStringContainsString('"id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT', $statements[1]);
        $this->assertStringContainsString('"name" varchar(100) NOT NULL', $statements[1]);
        $this->assertStringNotContainsString('legacy', $statements[1]);

        // 3. INSERT INTO temp (surviving columns only) SELECT ... FROM users
        $this->assertStringContainsString('INSERT INTO "__htemp_', $statements[2]);
        $this->assertStringContainsString('("id", "name")', $statements[2]);
        $this->assertStringContainsString('FROM "users"', $statements[2]);

        // 4. DROP original
        $this->assertSame('DROP TABLE "users"', $statements[3]);

        // 5. RENAME temp -> users
        $this->assertStringContainsString('RENAME TO "users"', $statements[4]);

        // 6. Recreate the non-primary index on the final table
        $this->assertSame('CREATE INDEX "idx_name" ON "users" ("name")', $statements[5]);

        // 7. PRAGMA on
        $this->assertSame('PRAGMA foreign_keys = ON', $statements[6]);
    }

    /**
     * When FK checks are managed by the plan, the rebuild must NOT emit its own
     * PRAGMA statements (to avoid duplication / premature re-enabling).
     */
    public function testRebuildSkipsPragmaWhenForeignKeyChecksAreManaged(): void
    {
        $dialect = new SqliteDialect();
        $context = new CompilationContext($this->schemaWithUsers(), true);

        $alter = new AlterTable('users');
        $alter->dropColumn('legacy');

        $statements = iterator_to_array($dialect->compileAlterTable($alter, $context), false);

        $this->assertNotContains('PRAGMA foreign_keys = OFF', $statements);
        $this->assertNotContains('PRAGMA foreign_keys = ON', $statements);
        // The rebuild body still starts with the CREATE TABLE temp.
        $this->assertStringStartsWith('CREATE TABLE "__htemp_', $statements[0]);
    }
}
