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

use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TablePlan;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractCompilerTestCase.
 *
 * Defines all compilation scenarios via a data provider.
 * Subclasses provide the compiler and expected SQL via a single switch.
 */
abstract class AbstractCompilerTestCase extends TestCase
{
    /**
     * Get the compiler under test.
     *
     * @return CompilerInterface
     */
    abstract protected function getCompiler(): CompilerInterface;

    /**
     * Return expected SQL for a given scenario.
     *
     * Must return:
     * - string: a single expected SQL statement
     * - string[]: multiple expected SQL statements
     * - null: after calling $this->markTestSkipped() or $this->expectException()
     *
     * Default case must throw \Exception for unimplemented scenarios.
     *
     * @param string $scenario
     *
     * @return array|string|null
     */
    abstract protected function expected(string $scenario): array|string|null;

    /**
     * Provide all compilation scenarios.
     *
     * @return iterable<string, array{string, Plan}>
     */
    public function scenarios(): iterable
    {
        // CREATE TABLE
        yield 'createTableSimple' => ['createTableSimple', $this->buildPlan(function (Plan $plan) {
            $plan->create('posts', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('title', 'varchar(255)')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
            });
        })];

        yield 'createTableWithOptions' => ['createTableWithOptions', $this->buildPlan(function (Plan $plan) {
            $plan->create('posts', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('title', 'varchar(255)')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
            }, charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci');
        })];

        yield 'createTableWithForeignKey' => ['createTableWithForeignKey', $this->buildPlan(function (Plan $plan) {
            $plan->create('posts', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('user_id', 'int')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                  ->addForeignKey('fk_posts_user', ['user_id'], 'users', ['id'], onDelete: ForeignKey::RULE_CASCADE);
            });
        })];

        yield 'createTableMultipleIndexes' => ['createTableMultipleIndexes', $this->buildPlan(function (Plan $plan) {
            $plan->create('users', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('email', 'varchar(255)')
                  ->addColumn('name', 'varchar(100)')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                  ->addIndex('idx_email', ['email'], Index::UNIQUE)
                  ->addIndex('idx_name', ['name']);
            });
        })];

        yield 'createTableWithoutAutoIncrement' => ['createTableWithoutAutoIncrement', $this->buildPlan(function (Plan $plan) {
            $plan->create('categories', function (TablePlan $t) {
                $t->addColumn('id', 'INTEGER')
                  ->addColumn('name', 'TEXT')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
            });
        })];

        yield 'createTableIfNotExists' => ['createTableIfNotExists', $this->buildPlan(function (Plan $plan) {
            $plan->create('posts', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('title', 'varchar(255)')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
            }, ifNotExists: true);
        })];

        // DROP / RENAME TABLE
        yield 'dropTable' => ['dropTable', $this->buildPlan(function (Plan $plan) {
            $plan->drop('posts');
        })];

        yield 'dropTableIfExists' => ['dropTableIfExists', $this->buildPlan(function (Plan $plan) {
            $plan->drop('posts', ifExists: true);
        })];

        yield 'renameTable' => ['renameTable', $this->buildPlan(function (Plan $plan) {
            $plan->rename('old_table', 'new_table');
        })];

        // ALTER TABLE — columns
        yield 'alterAddColumns' => ['alterAddColumns', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')
                ->addColumn('email', 'varchar(255)', hasDefault: true, default: '')
                ->addColumn('phone', 'varchar(20)', nullable: true);
        })];

        yield 'alterDropColumn' => ['alterDropColumn', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->dropColumn('old_field');
        })];

        yield 'alterModifyColumn' => ['alterModifyColumn', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->modifyColumn('name', 'varchar(500)');
        })];

        yield 'alterModifyColumnWithAfter' => ['alterModifyColumnWithAfter', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->modifyColumn('email', 'varchar(500)', after: 'name');
        })];

        yield 'alterRenameColumn' => ['alterRenameColumn', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->renameColumn('fullname', 'display_name');
        })];

        // ALTER TABLE — indexes
        yield 'alterAddIndex' => ['alterAddIndex', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addIndex('idx_name', ['name']);
        })];

        yield 'alterAddUniqueIndex' => ['alterAddUniqueIndex', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addIndex('idx_email', ['email'], Index::UNIQUE);
        })];

        yield 'alterDropIndex' => ['alterDropIndex', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->dropIndex('idx_old');
        })];

        // ALTER TABLE — foreign keys
        yield 'alterAddForeignKey' => ['alterAddForeignKey', $this->buildPlan(function (Plan $plan) {
            $plan->alter('posts')
                ->addForeignKey('fk_author', ['author_id'], 'users', ['id'], onDelete: ForeignKey::RULE_CASCADE);
        })];

        yield 'alterDropForeignKey' => ['alterDropForeignKey', $this->buildPlan(function (Plan $plan) {
            $plan->alter('posts')->dropForeignKey('fk_author');
        })];

        // ALTER TABLE — mixed operations
        yield 'alterMixedOperations' => ['alterMixedOperations', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')
                ->addColumn('email', 'varchar(255)', hasDefault: true, default: '')
                ->dropColumn('legacy')
                ->renameColumn('fullname', 'display_name')
                ->addIndex('idx_email', ['email'], Index::UNIQUE);
        })];

        // Column options
        yield 'columnWithDefault' => ['columnWithDefault', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('status', 'varchar(20)', hasDefault: true, default: 'active');
        })];

        yield 'columnDefaultBoolean' => ['columnDefaultBoolean', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('active', 'tinyint(1)', hasDefault: true, default: true);
        })];

        yield 'columnDefaultInteger' => ['columnDefaultInteger', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('score', 'int', hasDefault: true, default: 0);
        })];

        yield 'columnNullable' => ['columnNullable', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('bio', 'text', nullable: true);
        })];

        yield 'columnNullableWithNullDefault' => ['columnNullableWithNullDefault', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('deleted_at', 'datetime', nullable: true);
        })];

        yield 'columnAutoIncrement' => ['columnAutoIncrement', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('id', 'int', autoIncrement: true);
        })];

        yield 'columnAfter' => ['columnAfter', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('email', 'varchar(255)', hasDefault: true, default: '', after: 'name');
        })];

        yield 'columnFirst' => ['columnFirst', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('id', 'int', autoIncrement: true, first: true);
        })];

        // Edge cases
        yield 'emptyPlan' => ['emptyPlan', new Plan()];

        yield 'emptyTablePlan' => ['emptyTablePlan', $this->buildPlan(function (Plan $plan) {
            // alter without operations — creates a TablePlan but adds nothing
            $plan->alter('users');
        })];

        yield 'multipleTablePlans' => ['multipleTablePlans', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
            $plan->alter('posts')->dropColumn('legacy');
        })];

        // ALTER TABLE — mixed with rebuild-triggering operation
        yield 'alterMixedWithRebuild' => ['alterMixedWithRebuild', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')
                ->addColumn('avatar', 'varchar(255)', nullable: true)
                ->dropColumn('legacy')
                ->modifyColumn('name', 'TEXT')
                ->addColumn('bio', 'text', nullable: true)
                ->dropColumn('temp');
        })];

        // FK ordering — FK should be emitted after structure
        yield 'fkOrderingCreateTables' => ['fkOrderingCreateTables', $this->buildPlan(function (Plan $plan) {
            // posts references users, but users is created second — FK ordering makes it work
            $plan->create('posts', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('user_id', 'int')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                  ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
            });
            $plan->create('users', function (TablePlan $t) {
                $t->addColumn('id', 'int', autoIncrement: true)
                  ->addColumn('name', 'varchar(100)')
                  ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
            });
        })];

        yield 'fkOrderingDropBeforeStructure' => ['fkOrderingDropBeforeStructure', $this->buildPlan(function (Plan $plan) {
            // Drop FK then drop column in same alter — DropFK should come first
            $plan->alter('posts')
                ->dropForeignKey('fk_user')
                ->dropColumn('user_id');
        })];

        // Validation — NOT NULL without default on ALTER
        yield 'alterAddColumnNotNullWithoutDefault' => ['alterAddColumnNotNullWithoutDefault', $this->buildPlan(function (Plan $plan) {
            $plan->alter('users')->addColumn('avatar', 'varchar(255)');
        })];

        // MIGRATE DATA
        yield 'migrateAllColumns' => ['migrateAllColumns', $this->buildPlan(function (Plan $plan) {
            $plan->migrate('users', 'users_v2');
        })];

        yield 'migrateWithMapping' => ['migrateWithMapping', $this->buildPlan(function (Plan $plan) {
            $plan->migrate('users', 'users_v2', ['id' => 'id', 'name' => 'full_name']);
        })];

        // VIEW operations
        yield 'createView' => ['createView', $this->buildPlan(function (Plan $plan) {
            $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');
        })];

        yield 'createViewOrReplace' => ['createViewOrReplace', $this->buildPlan(function (Plan $plan) {
            $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', orReplace: true);
        })];

        yield 'createViewWithAlgorithm' => ['createViewWithAlgorithm', $this->buildPlan(function (Plan $plan) {
            $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', algorithm: 'MERGE');
        })];

        yield 'dropView' => ['dropView', $this->buildPlan(function (Plan $plan) {
            $plan->dropView('old_view');
        })];

        yield 'dropViewIfExists' => ['dropViewIfExists', $this->buildPlan(function (Plan $plan) {
            $plan->dropView('old_view', ifExists: true);
        })];

        yield 'alterView' => ['alterView', $this->buildPlan(function (Plan $plan) {
            $plan->alterView('my_view', 'SELECT id, name FROM users');
        })];

        yield 'alterViewWithAlgorithm' => ['alterViewWithAlgorithm', $this->buildPlan(function (Plan $plan) {
            $plan->alterView('my_view', 'SELECT id, name FROM users', algorithm: 'TEMPTABLE');
        })];
    }

    /**
     * @dataProvider scenarios
     */
    public function testScenario(string $scenario, Plan $plan): void
    {
        $expected = $this->expected($scenario);

        $result = iterator_to_array($plan->getStatements($this->getCompiler()), false);

        if (null !== $expected) {
            $this->assertSame((array)$expected, $result);
        }
    }

    /**
     * Build a plan from a callback.
     *
     * @param callable $callback
     *
     * @return Plan
     */
    private function buildPlan(callable $callback): Plan
    {
        $plan = new Plan();
        $callback($plan);

        return $plan;
    }
}
