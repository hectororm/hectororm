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
use Hector\Schema\Plan\Operation\AlterView;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropView;
use Hector\Schema\Plan\Operation\MigrateData;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TablePlan;
use Hector\Schema\Plan\ViewPlan;
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

    public function testCreateReturnsTablePlan(): void
    {
        $plan = new Plan();
        $result = $plan->create('users');

        $this->assertInstanceOf(TablePlan::class, $result);
        $this->assertSame('users', $result->getName());
        $this->assertFalse($plan->isEmpty());
        $this->assertCount(1, $plan);
    }

    public function testCreateWithCallbackReturnsPlan(): void
    {
        $plan = new Plan();
        $called = false;

        $result = $plan->create('users', function (TablePlan $t) use (&$called) {
            $called = true;
            $this->assertSame('users', $t->getName());
            $t->addColumn('id', 'int', autoIncrement: true);
        });

        $this->assertTrue($called);
        $this->assertSame($plan, $result);
        $this->assertCount(1, $plan);
    }

    public function testAlterReturnsTablePlan(): void
    {
        $plan = new Plan();
        $result = $plan->alter('users');

        $this->assertInstanceOf(TablePlan::class, $result);
        $this->assertSame('users', $result->getName());
        $this->assertSame('users', $result->getName());
        $this->assertCount(1, $plan);
    }

    public function testAlterWithCallbackReturnsPlan(): void
    {
        $plan = new Plan();
        $called = false;

        $result = $plan->alter('users', function (TablePlan $t) use (&$called) {
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

        $this->assertInstanceOf(TablePlan::class, $result);
        $this->assertSame('users', $result->getName());
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
        $this->assertSame('legacy', $plan->getArrayCopy()[0]->getName());
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

    public function testCountReflectsTablePlans(): void
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

        $tablePlans = $plan->getArrayCopy();

        $this->assertCount(3, $tablePlans);
        $this->assertSame('users', $tablePlans[0]->getName());
        $this->assertSame('posts', $tablePlans[1]->getName());
        $this->assertSame('old_table', $tablePlans[2]->getName());
    }

    public function testIteratorAggregate(): void
    {
        $plan = new Plan();
        $this->assertInstanceOf(IteratorAggregate::class, $plan);

        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->alter('posts')->dropColumn('legacy');

        $tablePlans = [];
        foreach ($plan as $tablePlan) {
            $tablePlans[] = $tablePlan;
        }

        $this->assertCount(2, $tablePlans);
        $this->assertSame('users', $tablePlans[0]->getName());
        $this->assertSame('posts', $tablePlans[1]->getName());
    }

    public function testMultipleAlterOnSameTableCreatesMultipleTablePlans(): void
    {
        $plan = new Plan();
        $plan->alter('users')->addColumn('email', 'varchar(255)');
        $plan->alter('users')->addColumn('phone', 'varchar(20)');

        $this->assertCount(2, $plan);

        $tablePlans = $plan->getArrayCopy();
        $this->assertSame('users', $tablePlans[0]->getName());
        $this->assertSame('users', $tablePlans[1]->getName());
        $this->assertNotSame($tablePlans[0], $tablePlans[1]);
    }

    public function testChainingCreateAlterDropRename(): void
    {
        $plan = new Plan();

        $plan->create('comments', function (TablePlan $t) {
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

        $tablePlans = $plan->getArrayCopy();
        $this->assertCount(1, $tablePlans);
        $this->assertSame('users', $tablePlans[0]->getName());

        $ops = $tablePlans[0]->getArrayCopy();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(MigrateData::class, $ops[0]);
        $this->assertSame('users', $ops[0]->getObjectName());
        $this->assertSame('users_v2', $ops[0]->getTargetTable());
        $this->assertSame([], $ops[0]->getColumnMapping());
    }

    public function testMigrateWithMapping(): void
    {
        $plan = new Plan();
        $plan->migrate('users', 'users_v2', ['id' => 'id', 'name' => 'full_name']);

        $ops = $plan->getArrayCopy()[0]->getArrayCopy();
        $this->assertInstanceOf(MigrateData::class, $ops[0]);
        $this->assertSame(['id' => 'id', 'name' => 'full_name'], $ops[0]->getColumnMapping());
    }

    public function testMigrateAcceptsTableObjects(): void
    {
        $source = new Table('mydb', Table::TYPE_TABLE, 'users');
        $target = new Table('mydb', Table::TYPE_TABLE, 'users_v2');

        $plan = new Plan();
        $plan->migrate($source, $target);

        $ops = $plan->getArrayCopy()[0]->getArrayCopy();
        $this->assertInstanceOf(MigrateData::class, $ops[0]);
        $this->assertSame('users', $ops[0]->getObjectName());
        $this->assertSame('users_v2', $ops[0]->getTargetTable());
    }

    public function testMigrateCountsAsTablePlan(): void
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

    public function testCreateViewCreatesViewPlan(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');

        $objectPlans = $plan->getArrayCopy();
        $this->assertCount(1, $objectPlans);
        $this->assertInstanceOf(ViewPlan::class, $objectPlans[0]);
        $this->assertSame('active_users', $objectPlans[0]->getName());

        $ops = $objectPlans[0]->getArrayCopy();
        $this->assertCount(1, $ops);
        $this->assertInstanceOf(CreateView::class, $ops[0]);
        $this->assertSame('active_users', $ops[0]->getObjectName());
        $this->assertSame('SELECT * FROM users WHERE active = 1', $ops[0]->getStatement());
        $this->assertFalse($ops[0]->orReplace());
        $this->assertNull($ops[0]->getAlgorithm());
    }

    public function testCreateViewWithOrReplace(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', orReplace: true);

        $ops = $plan->getArrayCopy()[0]->getArrayCopy();
        $this->assertTrue($ops[0]->orReplace());
    }

    public function testCreateViewWithAlgorithm(): void
    {
        $plan = new Plan();
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1', algorithm: 'MERGE');

        $ops = $plan->getArrayCopy()[0]->getArrayCopy();
        $this->assertSame('MERGE', $ops[0]->getAlgorithm());
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

    public function testDropViewCreatesViewPlan(): void
    {
        $plan = new Plan();
        $plan->dropView('old_view', ifExists: true);

        $objectPlans = $plan->getArrayCopy();
        $this->assertInstanceOf(ViewPlan::class, $objectPlans[0]);

        $ops = $objectPlans[0]->getArrayCopy();
        $this->assertInstanceOf(DropView::class, $ops[0]);
        $this->assertSame('old_view', $ops[0]->getObjectName());
        $this->assertTrue($ops[0]->ifExists());
    }

    public function testDropViewAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_VIEW, 'old_view');
        $plan = new Plan();
        $plan->dropView($table);

        $this->assertSame('old_view', $plan->getArrayCopy()[0]->getName());
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

    public function testAlterViewCreatesViewPlan(): void
    {
        $plan = new Plan();
        $plan->alterView('my_view', 'SELECT id, name FROM users');

        $objectPlans = $plan->getArrayCopy();
        $this->assertInstanceOf(ViewPlan::class, $objectPlans[0]);

        $ops = $objectPlans[0]->getArrayCopy();
        $this->assertInstanceOf(AlterView::class, $ops[0]);
        $this->assertSame('my_view', $ops[0]->getObjectName());
        $this->assertSame('SELECT id, name FROM users', $ops[0]->getStatement());
        $this->assertNull($ops[0]->getAlgorithm());
    }

    public function testAlterViewAcceptsTableObject(): void
    {
        $table = new Table('mydb', Table::TYPE_VIEW, 'my_view');
        $plan = new Plan();
        $plan->alterView($table, 'SELECT id FROM users');

        $this->assertSame('my_view', $plan->getArrayCopy()[0]->getName());
    }

    public function testAlterViewWithAlgorithm(): void
    {
        $plan = new Plan();
        $plan->alterView('my_view', 'SELECT id FROM users', algorithm: 'TEMPTABLE');

        $ops = $plan->getArrayCopy()[0]->getArrayCopy();
        $this->assertSame('TEMPTABLE', $ops[0]->getAlgorithm());
    }

    // =========================================================================
    // View + Table ordering
    // =========================================================================

    public function testViewAndTableMixedCount(): void
    {
        $plan = new Plan();
        $plan->create('users', function (TablePlan $t) {
            $t->addColumn('id', 'int', autoIncrement: true);
        });
        $plan->createView('active_users', 'SELECT * FROM users WHERE active = 1');
        $plan->dropView('old_view');

        $this->assertCount(3, $plan);
    }
}
