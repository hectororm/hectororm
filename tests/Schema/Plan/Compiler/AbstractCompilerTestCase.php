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
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TableOperation;
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
        yield 'createTableSimple' => [
            'createTableSimple',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('title', 'varchar(255)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                });
            })
        ];

        yield 'createTableWithOptions' => [
            'createTableWithOptions',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('title', 'varchar(255)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                }, charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci');
            })
        ];

        yield 'createTableWithForeignKey' => [
            'createTableWithForeignKey',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('user_id', 'int')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->addForeignKey('fk_posts_user', ['user_id'], 'users', ['id'],
                            onDelete: ForeignKey::RULE_CASCADE);
                });
            })
        ];

        yield 'createTableMultipleIndexes' => [
            'createTableMultipleIndexes',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('users', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('email', 'varchar(255)')
                        ->addColumn('name', 'varchar(100)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->addIndex('idx_email', ['email'], Index::UNIQUE)
                        ->addIndex('idx_name', ['name']);
                });
            })
        ];

        yield 'createTableWithoutAutoIncrement' => [
            'createTableWithoutAutoIncrement',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('categories', function (TableOperation $t) {
                    $t->addColumn('id', 'INTEGER')
                        ->addColumn('name', 'TEXT')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                });
            })
        ];

        yield 'createTableIfNotExists' => [
            'createTableIfNotExists',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('title', 'varchar(255)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                }, ifNotExists: true);
            })
        ];

        // DROP / RENAME TABLE
        yield 'dropTable' => [
            'dropTable',
            $this->buildPlan(function (Plan $plan) {
                $plan->drop('posts');
            })
        ];

        yield 'dropTableIfExists' => [
            'dropTableIfExists',
            $this->buildPlan(function (Plan $plan) {
                $plan->drop('posts', ifExists: true);
            })
        ];

        yield 'renameTable' => [
            'renameTable',
            $this->buildPlan(function (Plan $plan) {
                $plan->rename('old_table', 'new_table');
            })
        ];

        // ALTER TABLE — columns
        yield 'alterAddColumns' => [
            'alterAddColumns',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')
                    ->addColumn('email', 'varchar(255)', hasDefault: true, default: '')
                    ->addColumn('phone', 'varchar(20)', nullable: true);
            })
        ];

        yield 'alterDropColumn' => [
            'alterDropColumn',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->dropColumn('old_field');
            })
        ];

        yield 'alterModifyColumn' => [
            'alterModifyColumn',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->modifyColumn('name', 'varchar(500)');
            })
        ];

        yield 'alterModifyColumnWithAfter' => [
            'alterModifyColumnWithAfter',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->modifyColumn('email', 'varchar(500)', after: 'name');
            })
        ];

        yield 'alterRenameColumn' => [
            'alterRenameColumn',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->renameColumn('fullname', 'display_name');
            })
        ];

        // ALTER TABLE — indexes
        yield 'alterAddIndex' => [
            'alterAddIndex',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addIndex('idx_name', ['name']);
            })
        ];

        yield 'alterAddUniqueIndex' => [
            'alterAddUniqueIndex',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addIndex('idx_email', ['email'], Index::UNIQUE);
            })
        ];

        yield 'alterDropIndex' => [
            'alterDropIndex',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->dropIndex('idx_old');
            })
        ];

        // ALTER TABLE — foreign keys
        yield 'alterAddForeignKey' => [
            'alterAddForeignKey',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('posts')
                    ->addForeignKey('fk_author', ['author_id'], 'users', ['id'], onDelete: ForeignKey::RULE_CASCADE);
            })
        ];

        yield 'alterDropForeignKey' => [
            'alterDropForeignKey',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('posts')->dropForeignKey('fk_author');
            })
        ];

        // ALTER TABLE — mixed operations
        yield 'alterMixedOperations' => [
            'alterMixedOperations',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')
                    ->addColumn('email', 'varchar(255)', hasDefault: true, default: '')
                    ->dropColumn('legacy')
                    ->renameColumn('fullname', 'display_name')
                    ->addIndex('idx_email', ['email'], Index::UNIQUE);
            })
        ];

        // Column options
        yield 'columnWithDefault' => [
            'columnWithDefault',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('status', 'varchar(20)', hasDefault: true, default: 'active');
            })
        ];

        yield 'columnDefaultBoolean' => [
            'columnDefaultBoolean',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('active', 'tinyint(1)', hasDefault: true, default: true);
            })
        ];

        yield 'columnDefaultInteger' => [
            'columnDefaultInteger',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('score', 'int', hasDefault: true, default: 0);
            })
        ];

        yield 'columnNullable' => [
            'columnNullable',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('bio', 'text', nullable: true);
            })
        ];

        yield 'columnNullableWithNullDefault' => [
            'columnNullableWithNullDefault',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('deleted_at', 'datetime', nullable: true);
            })
        ];

        yield 'columnAutoIncrement' => [
            'columnAutoIncrement',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('id', 'int', autoIncrement: true);
            })
        ];

        yield 'columnAfter' => [
            'columnAfter',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('email', 'varchar(255)', hasDefault: true, default: '', after: 'name');
            })
        ];

        yield 'columnFirst' => [
            'columnFirst',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('id', 'int', autoIncrement: true, first: true);
            })
        ];

        // Edge cases
        yield 'emptyPlan' => ['emptyPlan', new Plan()];

        yield 'emptyTablePlan' => [
            'emptyTablePlan',
            $this->buildPlan(function (Plan $plan) {
                // alter without operations — creates a TablePlan but adds nothing
                $plan->alter('users');
            })
        ];

        yield 'multipleTablePlans' => [
            'multipleTablePlans',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
                $plan->alter('posts')->dropColumn('legacy');
            })
        ];

        // ALTER TABLE — mixed with rebuild-triggering operation
        yield 'alterMixedWithRebuild' => [
            'alterMixedWithRebuild',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')
                    ->addColumn('avatar', 'varchar(255)', nullable: true)
                    ->dropColumn('legacy')
                    ->modifyColumn('name', 'TEXT')
                    ->addColumn('bio', 'text', nullable: true)
                    ->dropColumn('temp');
            })
        ];

        // FK ordering — FK should be emitted after structure
        yield 'fkOrderingCreateTables' => [
            'fkOrderingCreateTables',
            $this->buildPlan(function (Plan $plan) {
                // posts references users, but users is created second — FK ordering makes it work
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('user_id', 'int')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
                });
                $plan->create('users', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('name', 'varchar(100)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                });
            })
        ];

        yield 'fkOrderingDropBeforeStructure' => [
            'fkOrderingDropBeforeStructure',
            $this->buildPlan(function (Plan $plan) {
                // Drop FK then drop column in same alter — DropFK should come first
                $plan->alter('posts')
                    ->dropForeignKey('fk_user')
                    ->dropColumn('user_id');
            })
        ];

        // Validation — NOT NULL without default on ALTER
        yield 'alterAddColumnNotNullWithoutDefault' => [
            'alterAddColumnNotNullWithoutDefault',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->addColumn('avatar', 'varchar(255)');
            })
        ];

        // MIGRATE DATA
        yield 'migrateAllColumns' => [
            'migrateAllColumns',
            $this->buildPlan(function (Plan $plan) {
                $plan->migrate('users', 'users_v2');
            })
        ];

        yield 'migrateWithMapping' => [
            'migrateWithMapping',
            $this->buildPlan(function (Plan $plan) {
                $plan->migrate('users', 'users_v2', ['id' => 'id', 'name' => 'full_name']);
            })
        ];

        // RAW statements
        yield 'rawSimple' => [
            'rawSimple',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('CREATE FULLTEXT INDEX ft_search ON articles (title, body)');
            })
        ];

        yield 'rawBetweenOperations' => [
            'rawBetweenOperations',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')
                    ->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
                $plan->raw('CREATE FULLTEXT INDEX ft_email ON users (email)');
                $plan->alter('posts')->dropColumn('legacy');
            })
        ];

        yield 'rawMultiple' => [
            'rawMultiple',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('SET @OLD_SQL_MODE=@@SQL_MODE');
                $plan->raw('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
            })
        ];

        yield 'rawWithFkOrdering' => [
            'rawWithFkOrdering',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('users', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                });
                $plan->raw('CREATE FULLTEXT INDEX ft_name ON users (name)');
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('user_id', 'int')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
                });
            })
        ];

        yield 'rawOnly' => [
            'rawOnly',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('ALTER TABLE users ENGINE = InnoDB');
            })
        ];

        // VIEW operations
        yield 'createView' => [
            'createView',
            $this->buildPlan(function (Plan $plan) {
                $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');
            })
        ];

        yield 'createViewOrReplace' => [
            'createViewOrReplace',
            $this->buildPlan(function (Plan $plan) {
                $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', orReplace: true);
            })
        ];

        yield 'createViewWithAlgorithm' => [
            'createViewWithAlgorithm',
            $this->buildPlan(function (Plan $plan) {
                $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', algorithm: 'MERGE');
            })
        ];

        yield 'dropView' => [
            'dropView',
            $this->buildPlan(function (Plan $plan) {
                $plan->dropView('old_view');
            })
        ];

        yield 'dropViewIfExists' => [
            'dropViewIfExists',
            $this->buildPlan(function (Plan $plan) {
                $plan->dropView('old_view', ifExists: true);
            })
        ];

        yield 'alterView' => [
            'alterView',
            $this->buildPlan(function (Plan $plan) {
                $plan->alterView('my_view', 'SELECT id, name FROM users');
            })
        ];

        yield 'alterViewWithAlgorithm' => [
            'alterViewWithAlgorithm',
            $this->buildPlan(function (Plan $plan) {
                $plan->alterView('my_view', 'SELECT id, name FROM users', algorithm: 'TEMPTABLE');
            })
        ];

        // TRIGGER operations
        yield 'createTrigger' => [
            'createTrigger',
            $this->buildPlan(function (Plan $plan) {
                $plan->createTrigger(
                    'trg_users_insert',
                    'users',
                    CreateTrigger::AFTER,
                    CreateTrigger::INSERT,
                    'INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\')',
                );
            })
        ];

        yield 'createTriggerWithWhen' => [
            'createTriggerWithWhen',
            $this->buildPlan(function (Plan $plan) {
                $plan->createTrigger(
                    'trg_users_update',
                    'users',
                    CreateTrigger::BEFORE,
                    CreateTrigger::UPDATE,
                    'INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'update\')',
                    when: 'NEW.status != OLD.status',
                );
            })
        ];

        yield 'dropTrigger' => [
            'dropTrigger',
            $this->buildPlan(function (Plan $plan) {
                $plan->dropTrigger('trg_users_insert', 'users');
            })
        ];

        yield 'createTableWithTrigger' => [
            'createTableWithTrigger',
            $this->buildPlan(function (Plan $plan) {
                $plan->create('users', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('name', 'varchar(255)')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->createTrigger(
                            'trg_users_insert',
                            CreateTrigger::AFTER,
                            CreateTrigger::INSERT,
                            'INSERT INTO audit_log (table_name, action) VALUES (\'users\', \'insert\')',
                        );
                });
            })
        ];

        yield 'alterTableDropTrigger' => [
            'alterTableDropTrigger',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->dropTrigger('trg_users_insert');
            })
        ];

        // RAW with driver filter
        yield 'rawDriverMatch' => [
            'rawDriverMatch',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql']);
            })
        ];

        yield 'rawDriverMatchMultiple' => [
            'rawDriverMatchMultiple',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql', 'mariadb']);
            })
        ];

        yield 'rawDriverMismatch' => [
            'rawDriverMismatch',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['pgsql']);
            })
        ];

        yield 'rawDriverNull' => [
            'rawDriverNull',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('SELECT 1');
            })
        ];

        yield 'rawMixedDrivers' => [
            'rawMixedDrivers',
            $this->buildPlan(function (Plan $plan) {
                $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql']);
                $plan->raw('PRAGMA journal_mode = WAL', drivers: ['sqlite']);
                $plan->raw('SELECT 1');
            })
        ];

        // FK CHECKS operations
        yield 'disableFkChecks' => [
            'disableFkChecks',
            $this->buildPlan(function (Plan $plan) {
                $plan->add(new DisableForeignKeyChecks());
            })
        ];

        yield 'enableFkChecks' => [
            'enableFkChecks',
            $this->buildPlan(function (Plan $plan) {
                $plan->add(new EnableForeignKeyChecks());
            })
        ];

        yield 'fkChecksWrapping' => [
            'fkChecksWrapping',
            $this->buildPlan(function (Plan $plan) {
                $plan->add(new DisableForeignKeyChecks());
                $plan->create('users', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
                });
                $plan->add(new EnableForeignKeyChecks());
            })
        ];

        yield 'fkChecksWithForeignKey' => [
            'fkChecksWithForeignKey',
            $this->buildPlan(function (Plan $plan) {
                $plan->add(new DisableForeignKeyChecks());
                $plan->create('posts', function (TableOperation $t) {
                    $t->addColumn('id', 'int', autoIncrement: true)
                        ->addColumn('user_id', 'int')
                        ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                        ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
                });
                $plan->add(new EnableForeignKeyChecks());
            })
        ];

        // ALTER TABLE — modify charset
        yield 'alterModifyCharset' => [
            'alterModifyCharset',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->modifyCharset('utf8mb4');
            })
        ];

        yield 'alterModifyCharsetWithCollation' => [
            'alterModifyCharsetWithCollation',
            $this->buildPlan(function (Plan $plan) {
                $plan->alter('users')->modifyCharset('utf8mb4', 'utf8mb4_unicode_ci');
            })
        ];

        yield 'fkChecksWithTriggerAndFk' => [
            'fkChecksWithTriggerAndFk',
            $this->buildPlan(function (Plan $plan) {
                $plan->add(new DisableForeignKeyChecks());
                $plan->alter('posts', function ($table) {
                    $table->dropForeignKey('fk_old');
                    $table->addColumn('category_id', 'int', nullable: true);
                    $table->addForeignKey('fk_category', ['category_id'], 'categories', ['id']);
                    $table->createTrigger(
                        'trg_audit',
                        CreateTrigger::AFTER,
                        CreateTrigger::INSERT,
                        'INSERT INTO audit_log (action) VALUES (\'insert\')',
                    );
                });
                $plan->add(new EnableForeignKeyChecks());
            })
        ];
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
