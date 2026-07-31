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

use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\Compiler;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\TableOperation;
use Hector\Schema\Tests\Plan\Compiler\Fixtures\SpyDialect;
use PHPUnit\Framework\TestCase;

/**
 * Class CompilerTest.
 *
 * Tests the ordering-only orchestration of the compiler in isolation from any
 * real SQL, using a {@see SpyDialect} that emits identifiable markers.
 */
class CompilerTest extends TestCase
{
    /**
     * @param Plan $plan
     * @param SpyDialect|null $dialect
     *
     * @return string[]
     */
    private function compile(Plan $plan, ?SpyDialect $dialect = null): array
    {
        $compiler = new Compiler($dialect ?? new SpyDialect());

        return iterator_to_array($compiler->compile($plan), false);
    }

    public function testEmptyPlanYieldsNothing(): void
    {
        $this->assertSame([], $this->compile(new Plan()));
    }

    public function testStructureOperationsKeepDeclarationOrder(): void
    {
        $plan = new Plan();
        $plan->create('a', fn(TableOperation $t): TableOperation => $t->addColumn('id', 'int'));
        $plan->drop('b');
        $plan->create('c', fn(TableOperation $t): TableOperation => $t->addColumn('id', 'int'));

        $this->assertSame(
            ['CREATE:a', 'DROP:b', 'CREATE:c'],
            $this->compile($plan),
        );
    }

    public function testThreePassOrderingAcrossAutonomousEntries(): void
    {
        $plan = new Plan();
        $plan->add(new EnableForeignKeyChecks());   // Post
        $plan->drop('t');                           // Structure
        $plan->add(new DisableForeignKeyChecks());  // Pre

        // Pre first, then structure, then post — regardless of declaration order.
        $this->assertSame(
            ['FK_OFF', 'DROP:t', 'FK_ON'],
            $this->compile($plan),
        );
    }

    public function testGroupSubOperationsAreRoutedToPreStructureAndPostPasses(): void
    {
        // An alter table mixing a Pre (DROP FK), structure (ADD COLUMN) and
        // Post (ADD FK) sub-operation. The group body is emitted in the
        // structure pass, its Pre before and its Post after.
        $plan = new Plan();
        $plan->alter('posts', function (TableOperation $t): void {
            $t->dropForeignKey('fk_old');
            $t->addColumn('category_id', 'int', nullable: true);
            $t->addForeignKey('fk_new', ['category_id'], 'categories', ['id']);
        });

        $this->assertSame(
            ['DROP_FK:fk_old', 'ALTER:posts', 'ADD_FK:fk_new'],
            $this->compile($plan),
        );
    }

    public function testCreateTableForeignKeyIsEmittedAsPostByDefault(): void
    {
        $plan = new Plan();
        $plan->create('posts', function (TableOperation $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->addColumn('user_id', 'int')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
        });

        // Default dialect: FK is a separate Post statement after the CREATE.
        $this->assertSame(
            ['CREATE:posts', 'ADD_FK:fk_user'],
            $this->compile($plan),
        );
    }

    public function testInlineForeignKeysDialectSkipsPostForeignKeyOfCreateTable(): void
    {
        $plan = new Plan();
        $plan->create('posts', function (TableOperation $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->addColumn('user_id', 'int')
                ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
                ->addForeignKey('fk_user', ['user_id'], 'users', ['id']);
        });

        $dialect = new SpyDialect(inlinesCreateTableForeignKeys: true);

        // FK is inlined into CREATE by the dialect, so no separate ADD_FK.
        $this->assertSame(
            ['CREATE:posts'],
            $this->compile($plan, $dialect),
        );
    }

    public function testStandaloneForeignKeyOperationsAreSkippedWhenAlterFkUnsupported(): void
    {
        $plan = new Plan();
        $plan->alter('posts', function (TableOperation $t): void {
            $t->dropForeignKey('fk_old');
            $t->addColumn('category_id', 'int', nullable: true);
            $t->addForeignKey('fk_new', ['category_id'], 'categories', ['id']);
        });

        $dialect = new SpyDialect(supportsAlterForeignKey: false);

        // Both FK sub-operations are dropped; only the group body remains.
        $this->assertSame(
            ['ALTER:posts'],
            $this->compile($plan, $dialect),
        );
    }

    public function testTriggerOfCreateTableIsEmittedAfterStructure(): void
    {
        $plan = new Plan();
        $plan->create('users', function (TableOperation $t): void {
            $t->addColumn('id', 'int', autoIncrement: true)
                ->createTrigger('trg', CreateTrigger::AFTER, CreateTrigger::INSERT, 'SELECT 1');
        });

        $this->assertSame(
            ['CREATE:users', 'CREATE_TRIGGER:trg'],
            $this->compile($plan),
        );
    }

    public function testRawStatementEmittedForMatchingDriverOnly(): void
    {
        $plan = new Plan();
        $plan->raw('MATCH', drivers: ['spy']);
        $plan->raw('SKIP', drivers: ['other']);
        $plan->raw('ALL', drivers: null);

        // SpyDialect::driverNames() returns ['spy'].
        $this->assertSame(
            ['MATCH', 'ALL'],
            $this->compile($plan),
        );
    }

    public function testWholePlanOrderingWithFkChecksWrapping(): void
    {
        $plan = new Plan();
        $plan->add(new DisableForeignKeyChecks());
        $plan->alter('posts', function (TableOperation $t): void {
            $t->dropForeignKey('fk_old');
            $t->addColumn('category_id', 'int', nullable: true);
            $t->addForeignKey('fk_new', ['category_id'], 'categories', ['id']);
        });
        $plan->add(new EnableForeignKeyChecks());

        $this->assertSame(
            [
                // Pre pass: disable FK checks, then drop FK.
                'FK_OFF',
                'DROP_FK:fk_old',
                // Structure pass: the alter body.
                'ALTER:posts',
                // Post pass: add FK, then enable FK checks.
                'ADD_FK:fk_new',
                'FK_ON',
            ],
            $this->compile($plan),
        );
    }
}
