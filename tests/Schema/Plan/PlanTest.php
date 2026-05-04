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

namespace Hector\Schema\Tests\Plan;

use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropTrigger;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\RawStatement;
use Hector\Schema\Plan\TableOperation;
use Hector\Schema\Table;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;

/**
 * Class PlanTest.
 */
class PlanTest extends TestCase
{
    public function testEmptyPlan(): void
    {
        $plan = new Plan();

        $this->assertTrue($plan->isEmpty());
        $this->assertCount(0, $plan);
        $this->assertSame([], $plan->getArrayCopy());
    }

    public function testCreateReturnsCreateTable(): void
    {
        $plan = new Plan();
        $result = $plan->create('users');

        $this->assertInstanceOf(CreateTable::class, $result);
        $this->assertInstanceOf(TableOperation::class, $result);
        $this->assertSame('users', $result->getObjectName());
        $this->assertFalse($plan->isEmpty());
        $this->assertCount(1, $plan);
    }

    public function testCreateWithCallbackReturnsPlan(): void
    {
        $plan = new Plan();
        $called = false;

        $result = $plan->create('users', function (CreateTable $t) use (&$called): void {
            $called = true;
            $this->assertSame('users', $t->getObjectName());
            $t->addColumn('id', 'int', autoIncrement: true);
        });

        $this->assertTrue($called);
        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testAlterReturnsAlterTable(): void
    {
        $plan = new Plan();
        $result = $plan->alter('users');

        $this->assertInstanceOf(AlterTable::class, $result);
        $this->assertInstanceOf(TableOperation::class, $result);
        $this->assertSame('users', $result->getObjectName());
        $this->assertCount(1, $plan);
    }

    public function testAlterWithCallbackReturnsPlan(): void
    {
        $plan = new Plan();
        $called = false;

        $result = $plan->alter('users', function (AlterTable $t) use (&$called): void {
            $called = true;
            $t->addColumn('email', 'varchar(255)');
        });

        $this->assertTrue($called);
        $this->assertSame($plan, $result);
    }

    public function testAlterAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_TABLE, 'users');
        $plan = new Plan();
        $result = $plan->alter($table);

        $this->assertInstanceOf(AlterTable::class, $result);
        $this->assertSame('users', $result->getObjectName());
    }

    public function testDropReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->drop('legacy');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testDropAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_TABLE, 'legacy');
        $plan = new Plan();
        $plan->drop($table);

        $this->assertCount(1, $plan);

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(DropTable::class, $entry);
        $this->assertSame('legacy', $entry->getObjectName());
    }

    public function testRenameReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->rename('old', 'new');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testRenameAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_TABLE, 'old');
        $plan = new Plan();
        $plan->rename($table, 'new');

        $this->assertCount(1, $plan);
    }

    public function testCountReflectsTableOperations(): void
    {
        $plan = new Plan();
        $this->assertCount(0, $plan);

        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $this->assertCount(1, $plan);

        $plan->alter('posts')->dropColumn('legacy');
        $this->assertCount(2, $plan);

        $plan->drop('old_table');
        $this->assertCount(3, $plan);
    }

    public function testIsEmptyAfterOperations(): void
    {
        $plan = new Plan();
        $this->assertTrue($plan->isEmpty());

        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $this->assertFalse($plan->isEmpty());
    }

    public function testGetArrayCopyOrder(): void
    {
        $plan = new Plan();
        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->alter('posts')->dropColumn('legacy');
        $plan->drop('old_table');

        $entries = $plan->getArrayCopy();

        $this->assertCount(3, $entries);
        $this->assertSame('users', $entries[0]->getObjectName());
        $this->assertSame('posts', $entries[1]->getObjectName());
        $this->assertSame('old_table', $entries[2]->getObjectName());
    }

    public function testIteratorAggregate(): void
    {
        $plan = new Plan();
        $this->assertInstanceOf(IteratorAggregate::class, $plan);

        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->alter('posts')->dropColumn('legacy');

        $entries = [];
        foreach ($plan as $entry) {
            $entries[] = $entry;
        }

        $this->assertCount(2, $entries);
        $this->assertSame('users', $entries[0]->getObjectName());
        $this->assertSame('posts', $entries[1]->getObjectName());
    }

    public function testMultipleAlterOnSameTableCreatesMultipleEntries(): void
    {
        $plan = new Plan();
        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->alter('users')->addColumn('phone', 'varchar(20)');

        $this->assertCount(2, $plan);

        $entries = $plan->getArrayCopy();
        $this->assertSame('users', $entries[0]->getObjectName());
        $this->assertSame('users', $entries[1]->getObjectName());
        $this->assertNotSame($entries[0], $entries[1]);
    }

    public function testChainingCreateAlterDropRename(): void
    {
        $plan = new Plan();

        $plan->create('comments', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->addColumn('body', 'text')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
        });

        $plan->alter('users')
            ->addColumn('email', 'varchar(255)');

        $plan->rename('old_table', 'new_table');
        $plan->drop('legacy', ifExists: true);

        $this->assertCount(4, $plan);
    }

    public function testMigrateReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->migrate('users', 'users_v2');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testMigrateAllColumns(): void
    {
        $plan = new Plan();
        $plan->migrate('users', 'users_v2');

        $entries = $plan->getArrayCopy();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertInstanceOf(MigrateData::class, $entry);
        $this->assertSame('users', $entry->getObjectName());
        $this->assertSame('users_v2', $entry->getTargetTable());
        $this->assertSame([], $entry->getColumnMapping());
    }

    public function testMigrateWithMapping(): void
    {
        $plan = new Plan();
        $plan->migrate('users', 'users_v2', ['id' => 'id', 'name' => 'full_name']);

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(MigrateData::class, $entry);
        $this->assertSame(['id' => 'id', 'name' => 'full_name'], $entry->getColumnMapping());
    }

    public function testMigrateAcceptsTableObjects(): void
    {
        $source = new Table('mydb', Table::TYPE_TABLE, 'users');
        $target = new Table('mydb', Table::TYPE_TABLE, 'users_v2');

        $plan = new Plan();
        $plan->migrate($source, $target);

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(MigrateData::class, $entry);
        $this->assertSame('users', $entry->getObjectName());
        $this->assertSame('users_v2', $entry->getTargetTable());
    }

    public function testMigrateCountsAsEntry(): void
    {
        $plan = new Plan();
        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->migrate('old_data', 'new_data');
        $plan->drop('old_data');

        $this->assertCount(3, $plan);
        $this->assertFalse($plan->isEmpty());
    }

    // =========================================================================
    // createView
    // =========================================================================

    public function testCreateViewReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
        $this->assertFalse($plan->isEmpty());
    }

    public function testCreateViewCreatesAtomicEntry(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');

        $entries = $plan->getArrayCopy();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertInstanceOf(CreateView::class, $entry);
        $this->assertSame('active_users', $entry->getObjectName());
        $this->assertSame('SELECT * FROM users WHERE active = 1', $entry->getStatement());
        $this->assertFalse($entry->orReplace());
        $this->assertNull($entry->getAlgorithm());
    }

    public function testCreateViewWithOrReplace(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', orReplace: true);

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(CreateView::class, $entry);
        $this->assertTrue($entry->orReplace());
    }

    public function testCreateViewWithAlgorithm(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', algorithm: 'MERGE');

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(CreateView::class, $entry);
        $this->assertSame('MERGE', $entry->getAlgorithm());
    }

    // =========================================================================
    // dropView
    // =========================================================================

    public function testDropViewReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->dropView('old_view');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testDropViewCreatesAtomicEntry(): void
    {
        $plan = new Plan();
        $plan->dropView('old_view', ifExists: true);

        $entries = $plan->getArrayCopy();
        $entry = $entries[0];
        $this->assertInstanceOf(DropView::class, $entry);
        $this->assertSame('old_view', $entry->getObjectName());
        $this->assertTrue($entry->ifExists());
    }

    public function testDropViewAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_VIEW, 'old_view');
        $plan = new Plan();
        $plan->dropView($table);

        $this->assertSame('old_view', $plan->getArrayCopy()[0]->getObjectName());
    }

    // =========================================================================
    // alterView
    // =========================================================================

    public function testAlterViewReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->alterView('my_view', 'SELECT id, name FROM users');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testAlterViewCreatesAtomicEntry(): void
    {
        $plan = new Plan();
        $plan->alterView('my_view', 'SELECT id, name FROM users');

        $entries = $plan->getArrayCopy();
        $entry = $entries[0];
        $this->assertInstanceOf(AlterView::class, $entry);
        $this->assertSame('my_view', $entry->getObjectName());
        $this->assertSame('SELECT id, name FROM users', $entry->getStatement());
        $this->assertNull($entry->getAlgorithm());
    }

    public function testAlterViewAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_VIEW, 'my_view');
        $plan = new Plan();
        $plan->alterView($table, 'SELECT id FROM users');

        $this->assertSame('my_view', $plan->getArrayCopy()[0]->getObjectName());
    }

    public function testAlterViewWithAlgorithm(): void
    {
        $plan = new Plan();
        $plan->alterView('my_view', 'SELECT id FROM users', algorithm: 'TEMPTABLE');

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(AlterView::class, $entry);
        $this->assertSame('TEMPTABLE', $entry->getAlgorithm());
    }

    // =========================================================================
    // View + Table ordering
    // =========================================================================

    public function testViewAndTableMixedCount(): void
    {
        $plan = new Plan();
        $plan->create('users', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true);
        });
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');
        $plan->dropView('old_view');

        $this->assertCount(3, $plan);
    }

    // =========================================================================
    // raw
    // =========================================================================

    public function testRawReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->raw('CREATE FULLTEXT INDEX ft_name ON users (name)');

        $this->assertSame($plan, $result);
    }

    public function testRawCountsAsEntry(): void
    {
        $plan = new Plan();
        $plan->raw('ALTER TABLE users ENGINE = InnoDB');

        $this->assertCount(1, $plan);
        $this->assertFalse($plan->isEmpty());
    }

    public function testRawAppearsInGetArrayCopy(): void
    {
        $plan = new Plan();
        $plan->alter('users')->addColumn('email', 'varchar(255)', nullable: true);
        $plan->raw('CREATE FULLTEXT INDEX ft_email ON users (email)');

        $entries = $plan->getArrayCopy();

        $this->assertCount(2, $entries);
        $this->assertInstanceOf(AlterTable::class, $entries[0]);
        $this->assertInstanceOf(RawStatement::class, $entries[1]);
        $this->assertSame('CREATE FULLTEXT INDEX ft_email ON users (email)', $entries[1]->getStatement());
    }

    public function testRawAppearsInIterator(): void
    {
        $plan = new Plan();
        $plan->raw('SET FOREIGN_KEY_CHECKS = 0');
        $plan->alter('users')->addColumn('email', 'varchar(255)', nullable: true);
        $plan->raw('SET FOREIGN_KEY_CHECKS = 1');

        $entries = iterator_to_array($plan);

        $this->assertCount(3, $entries);
        $this->assertInstanceOf(RawStatement::class, $entries[0]);
        $this->assertInstanceOf(AlterTable::class, $entries[1]);
        $this->assertInstanceOf(RawStatement::class, $entries[2]);
    }

    public function testRawChainingWithOtherMethods(): void
    {
        $plan = new Plan();
        $plan->create('users', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->addColumn('name', 'varchar(255)')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY);
        })
            ->raw('CREATE FULLTEXT INDEX ft_name ON users (name)')
            ->drop('old_table');

        $this->assertCount(3, $plan);
    }

    public function testMultipleRawStatements(): void
    {
        $plan = new Plan();
        $plan->raw('SET @OLD_SQL_MODE=@@SQL_MODE');
        $plan->raw('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');

        $this->assertCount(2, $plan);

        $entries = $plan->getArrayCopy();
        $this->assertInstanceOf(RawStatement::class, $entries[0]);
        $this->assertInstanceOf(RawStatement::class, $entries[1]);
    }

    // =========================================================================
    // createTrigger
    // =========================================================================

    public function testCreateTriggerReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->createTrigger(
            'trg_audit',
            'users',
            CreateTrigger::AFTER,
            CreateTrigger::INSERT,
            'INSERT INTO audit_log (action) VALUES (\'insert\')',
        );

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
        $this->assertFalse($plan->isEmpty());
    }

    public function testCreateTriggerCreatesAtomicEntry(): void
    {
        $plan = new Plan();
        $plan->createTrigger(
            'trg_audit',
            'users',
            CreateTrigger::AFTER,
            CreateTrigger::INSERT,
            'INSERT INTO audit_log (action) VALUES (\'insert\')',
        );

        $entries = $plan->getArrayCopy();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertInstanceOf(CreateTrigger::class, $entry);
        $this->assertSame('users', $entry->getObjectName());
        $this->assertSame('trg_audit', $entry->getName());
        $this->assertSame(CreateTrigger::AFTER, $entry->getTiming());
        $this->assertSame(CreateTrigger::INSERT, $entry->getEvent());
        $this->assertSame('INSERT INTO audit_log (action) VALUES (\'insert\')', $entry->getBody());
        $this->assertNull($entry->getWhen());
    }

    public function testCreateTriggerWithWhen(): void
    {
        $plan = new Plan();
        $plan->createTrigger(
            'trg_check',
            'users',
            CreateTrigger::BEFORE,
            CreateTrigger::UPDATE,
            'INSERT INTO audit_log (action) VALUES (\'update\')',
            when: 'NEW.status != OLD.status',
        );

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(CreateTrigger::class, $entry);
        $this->assertSame('NEW.status != OLD.status', $entry->getWhen());
    }

    public function testCreateTriggerAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_TABLE, 'users');
        $plan = new Plan();
        $plan->createTrigger(
            'trg_audit',
            $table,
            CreateTrigger::AFTER,
            CreateTrigger::INSERT,
            'INSERT INTO audit_log (action) VALUES (\'insert\')',
        );

        $this->assertSame('users', $plan->getArrayCopy()[0]->getObjectName());
    }

    // =========================================================================
    // dropTrigger
    // =========================================================================

    public function testDropTriggerReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->dropTrigger('trg_audit', 'users');

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testDropTriggerCreatesAtomicEntry(): void
    {
        $plan = new Plan();
        $plan->dropTrigger('trg_audit', 'users');

        $entries = $plan->getArrayCopy();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertInstanceOf(DropTrigger::class, $entry);
        $this->assertSame('users', $entry->getObjectName());
        $this->assertSame('trg_audit', $entry->getName());
    }

    public function testDropTriggerAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_TABLE, 'users');
        $plan = new Plan();
        $plan->dropTrigger('trg_audit', $table);

        $this->assertSame('users', $plan->getArrayCopy()[0]->getObjectName());
    }

    // =========================================================================
    // Trigger inside CreateTable / AlterTable
    // =========================================================================

    public function testCreateTableWithTriggerCountsCorrectly(): void
    {
        $plan = new Plan();
        $plan->create('users', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                ->createTrigger(
                    'trg_audit',
                    CreateTrigger::AFTER,
                    CreateTrigger::INSERT,
                    'INSERT INTO audit_log (action) VALUES (\'insert\')',
                );
        });

        $this->assertCount(1, $plan);
    }

    public function testAlterTableDropTriggerCountsCorrectly(): void
    {
        $plan = new Plan();
        $plan->alter('users')->dropTrigger('trg_audit');

        $this->assertCount(1, $plan);
    }

    // =========================================================================
    // Trigger + Table mixed ordering
    // =========================================================================

    public function testTriggerAndTableMixedCount(): void
    {
        $plan = new Plan();
        $plan->create('users', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true);
        });
        $plan->createTrigger(
            'trg_audit',
            'users',
            CreateTrigger::AFTER,
            CreateTrigger::INSERT,
            'INSERT INTO audit_log (action) VALUES (\'insert\')',
        );
        $plan->dropTrigger('trg_old', 'users');

        $this->assertCount(3, $plan);
    }

    // =========================================================================
    // raw with driver filter
    // =========================================================================

    public function testRawWithDriversReturnsPlan(): void
    {
        $plan = new Plan();
        $result = $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql']);

        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testRawWithDriversCreatesEntry(): void
    {
        $plan = new Plan();
        $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql', 'mariadb']);

        $entries = $plan->getArrayCopy();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertInstanceOf(RawStatement::class, $entry);
        $this->assertSame('ALTER TABLE users ENGINE = InnoDB', $entry->getStatement());
        $this->assertSame(['mysql', 'mariadb'], $entry->getDrivers());
    }

    public function testRawWithoutDriversDefaultsToNull(): void
    {
        $plan = new Plan();
        $plan->raw('SELECT 1');

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(RawStatement::class, $entry);
        $this->assertNull($entry->getDrivers());
    }

    public function testRawWithDriversCountsAsEntry(): void
    {
        $plan = new Plan();
        $plan->raw('ALTER TABLE users ENGINE = InnoDB', drivers: ['mysql']);
        $plan->raw('PRAGMA journal_mode = WAL', drivers: ['sqlite']);
        $plan->raw('SELECT 1');

        $this->assertCount(3, $plan);
        $this->assertFalse($plan->isEmpty());
    }

    // =========================================================================
    // DisableForeignKeyChecks / EnableForeignKeyChecks
    // =========================================================================

    public function testDisableForeignKeyChecksAddsEntry(): void
    {
        $plan = new Plan();
        $plan->add(new DisableForeignKeyChecks());

        $this->assertCount(1, $plan);
        $this->assertFalse($plan->isEmpty());

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(DisableForeignKeyChecks::class, $entry);
        $this->assertNull($entry->getObjectName());
    }

    public function testEnableForeignKeyChecksAddsEntry(): void
    {
        $plan = new Plan();
        $plan->add(new EnableForeignKeyChecks());

        $this->assertCount(1, $plan);
        $this->assertFalse($plan->isEmpty());

        $entry = $plan->getArrayCopy()[0];
        $this->assertInstanceOf(EnableForeignKeyChecks::class, $entry);
        $this->assertNull($entry->getObjectName());
    }

    public function testFkChecksWrappingCount(): void
    {
        $plan = new Plan();
        $plan->add(new DisableForeignKeyChecks());
        $plan->create('users', function (CreateTable $t): void {
            $t->addColumn('id', 'int', autoIncrement: true);
        });
        $plan->drop('old_table');
        $plan->add(new EnableForeignKeyChecks());

        $this->assertCount(4, $plan);
    }
}
