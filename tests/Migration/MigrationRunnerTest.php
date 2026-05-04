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

namespace Hector\Migration\Tests;

use Hector\Connection\Connection;
use Hector\Migration\Direction;
use Hector\Migration\Event\MigrationAfterEvent;
use Hector\Migration\Event\MigrationBeforeEvent;
use Hector\Migration\Event\MigrationFailedEvent;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationRunner;
use Hector\Migration\Provider\ArrayProvider;
use Hector\Migration\Tests\Fake\AddPostsTableMigration;
use Hector\Migration\Tests\Fake\CreateUsersTableMigration;
use Hector\Migration\Tests\Fake\DescribedMigration;
use Hector\Migration\Tests\Fake\EmptyMigration;
use Hector\Migration\Tests\Fake\FailingMigration;
use Hector\Migration\Tests\Fake\IrreversibleMigration;
use Hector\Migration\Tracker\FileTracker;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class MigrationRunnerTest extends TestCase
{
    private Connection $connection;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hector_runner_test_') . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function createRunner(
        array $migrations,
        ?LoggerInterface $logger = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): MigrationRunner {
        return new MigrationRunner(
            provider: new ArrayProvider($migrations),
            tracker: new FileTracker($this->tempFile),
            compiler: new SqliteCompiler(),
            connection: $this->connection,
            logger: $logger,
            eventDispatcher: $eventDispatcher,
        );
    }

    public function testGetPending(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $pending = $runner->getPending();

        $this->assertCount(2, $pending);
        $this->assertArrayHasKey('create_users', $pending);
        $this->assertArrayHasKey('add_posts', $pending);
    }

    public function testGetAppliedEmpty(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ]);

        $this->assertEmpty($runner->getApplied());
    }

    public function testGetStatus(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $status = $runner->getStatus();

        $this->assertSame(['create_users', 'add_posts'], array_keys($status));
        $this->assertSame([false, false], array_values($status));
    }

    public function testUpAll(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $applied = $runner->up();

        $this->assertSame(['create_users', 'add_posts'], $applied);
        $this->assertEmpty($runner->getPending());
        $this->assertCount(2, $runner->getApplied());

        // Verify tables were actually created
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertNotNull($row);

        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        $this->assertNotNull($row);
    }

    public function testUpWithSteps(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $applied = $runner->up(steps: 1);

        $this->assertSame(['create_users'], $applied);
        $this->assertCount(1, $runner->getPending());
    }

    public function testUpIdempotent(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ]);

        $runner->up();
        $applied = $runner->up();

        $this->assertEmpty($applied);
    }

    public function testDown(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $runner->up();
        $reverted = $runner->down(steps: 1);

        $this->assertSame(['add_posts'], $reverted);
        $this->assertCount(1, $runner->getPending());

        // Verify posts table was dropped
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        $this->assertNull($row);

        // Users table still exists
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertNotNull($row);
    }

    public function testDownIrreversibleThrows(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'irreversible' => new IrreversibleMigration(),
        ]);

        $runner->up();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('cannot be reverted');

        $runner->down(steps: 1);
    }

    public function testEmptyMigration(): void
    {
        $runner = $this->createRunner([
            'empty' => new EmptyMigration(),
        ]);

        $applied = $runner->up();

        $this->assertSame(['empty'], $applied);
        $this->assertEmpty($runner->getPending());
    }

    public function testGetStatusAfterPartialUp(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $runner->up(steps: 1);
        $status = $runner->getStatus();

        $this->assertSame(['create_users', 'add_posts'], array_keys($status));
        $this->assertSame([true, false], array_values($status));
    }

    public function testUpStepsZero(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $applied = $runner->up(steps: 0);

        $this->assertEmpty($applied);
        $this->assertCount(2, $runner->getPending());
    }

    public function testDownMoreStepsThanApplied(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $runner->up();
        $reverted = $runner->down(steps: 10);

        $this->assertSame(['add_posts', 'create_users'], $reverted);
        $this->assertCount(2, $runner->getPending());
        $this->assertEmpty($runner->getApplied());
    }

    public function testMigrationFailureRollsBack(): void
    {
        // First, create the users table so the FailingMigration will conflict
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100))'
        );

        $runner = $this->createRunner([
            'failing' => new FailingMigration(),
        ]);

        try {
            $runner->up();
            $this->fail('Expected MigrationException was not thrown');
        } catch (MigrationException $e) {
            $this->assertStringContainsString('failed during up', $e->getMessage());
        }

        // Verify the migration was NOT tracked as applied
        $this->assertEmpty($runner->getApplied());
        $this->assertCount(1, $runner->getPending());
    }

    // --- Logger tests ---

    public function testLoggerReceivesInfoAndDebug(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->atLeastOnce())
            ->method('info');
        $logger->expects($this->atLeastOnce())
            ->method('debug');

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], logger: $logger);

        $runner->up();
    }

    public function testLoggerReceivesErrorOnFailure(): void
    {
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100))'
        );

        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->atLeastOnce())
            ->method('error');

        $runner = $this->createRunner([
            'failing' => new FailingMigration(),
        ], logger: $logger);

        try {
            $runner->up();
        } catch (MigrationException) {
            // Expected
        }
    }

    public function testRunnerWorksWithoutLogger(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ]);

        $applied = $runner->up();
        $this->assertSame(['create_users'], $applied);
    }

    // --- Event dispatcher tests ---

    public function testBeforeAndAfterEventsDispatched(): void
    {
        $dispatched = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched) {
                $dispatched[] = $event::class;

                return $event;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], eventDispatcher: $dispatcher);

        $runner->up();

        $this->assertContains(MigrationBeforeEvent::class, $dispatched);
        $this->assertContains(MigrationAfterEvent::class, $dispatched);
        $this->assertNotContains(MigrationFailedEvent::class, $dispatched);
    }

    public function testBeforeEventStoppedSkipsMigration(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) {
                if ($event instanceof MigrationBeforeEvent) {
                    $event->stopPropagation();
                }

                return $event;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], eventDispatcher: $dispatcher);

        $applied = $runner->up();

        // Migration was skipped, not returned as applied
        $this->assertEmpty($applied);

        // Migration is still pending (not tracked)
        $this->assertCount(1, $runner->getPending());

        // Table was NOT created
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertNull($row);
    }

    public function testFailedEventDispatchedOnError(): void
    {
        $this->connection->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100))'
        );

        $dispatched = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched) {
                $dispatched[] = $event;

                return $event;
            });

        $runner = $this->createRunner([
            'failing' => new FailingMigration(),
        ], eventDispatcher: $dispatcher);

        try {
            $runner->up();
        } catch (MigrationException $e) {
            // Expected
        }

        // Should have Before + Failed (no After)
        $classes = array_map('get_class', $dispatched);
        $this->assertContains(MigrationBeforeEvent::class, $classes);
        $this->assertContains(MigrationFailedEvent::class, $classes);
        $this->assertNotContains(MigrationAfterEvent::class, $classes);

        // Verify the FailedEvent carries the exception
        $failedEvents = array_filter($dispatched, fn($e): bool => $e instanceof MigrationFailedEvent);
        $failedEvent = reset($failedEvents);
        $this->assertInstanceOf(Throwable::class, $failedEvent->getException());
    }

    public function testAfterEventCarriesDuration(): void
    {
        $dispatched = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched) {
                $dispatched[] = $event;

                return $event;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], eventDispatcher: $dispatcher);

        $runner->up();

        $afterEvents = array_filter($dispatched, fn($e): bool => $e instanceof MigrationAfterEvent);
        $afterEvent = reset($afterEvents);

        $this->assertInstanceOf(MigrationAfterEvent::class, $afterEvent);
        $this->assertSame('create_users', $afterEvent->getMigrationId());
        $this->assertSame(Direction::UP, $afterEvent->getDirection());
        $this->assertIsFloat($afterEvent->getDurationMs());
    }

    public function testRunnerWorksWithoutEventDispatcher(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ]);

        $applied = $runner->up();
        $this->assertSame(['create_users'], $applied);
    }

    // --- Description in logs ---

    public function testLogIncludesDescriptionWhenPresent(): void
    {
        $messages = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(function (string $message) use (&$messages): void {
                $messages[] = $message;
            });

        $runner = $this->createRunner([
            'described' => new DescribedMigration(),
        ], logger: $logger);

        $runner->up();

        // At least one info message should contain the description
        $found = false;

        foreach ($messages as $msg) {
            if (str_contains($msg, 'A described migration')) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, 'Expected description "A described migration" in log messages');
    }

    public function testLogWithoutDescription(): void
    {
        $messages = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(function (string $message) use (&$messages): void {
                $messages[] = $message;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], logger: $logger);

        $runner->up();

        // Should contain the ID but no parenthesized description
        $found = false;

        foreach ($messages as $msg) {
            if (str_contains($msg, '"create_users"') && false === str_contains($msg, '(')) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, 'Expected log with ID "create_users" without description');
    }

    // --- Dry-run tests ---

    public function testDryRunDoesNotExecuteSql(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $applied = $runner->up(dryRun: true);

        $this->assertSame(['create_users', 'add_posts'], $applied);

        // Tables should NOT exist
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        $this->assertNull($row);

        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        $this->assertNull($row);
    }

    public function testDryRunDoesNotTrack(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ]);

        $runner->up(dryRun: true);

        // Migration should still be pending
        $this->assertCount(1, $runner->getPending());
        $this->assertEmpty($runner->getApplied());
    }

    public function testDryRunDispatchesAfterEvent(): void
    {
        $dispatched = [];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched) {
                $dispatched[] = $event;

                return $event;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], eventDispatcher: $dispatcher);

        $runner->up(dryRun: true);

        $afterEvents = array_filter($dispatched, fn($e): bool => $e instanceof MigrationAfterEvent);
        $afterEvent = reset($afterEvents);

        $this->assertInstanceOf(MigrationAfterEvent::class, $afterEvent);
        $this->assertSame('create_users', $afterEvent->getMigrationId());
        $this->assertSame(Direction::UP, $afterEvent->getDirection());
    }

    public function testDryRunLogsWithPrefix(): void
    {
        $messages = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('info')
            ->willReturnCallback(function (string $message) use (&$messages): void {
                $messages[] = $message;
            });

        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
        ], logger: $logger);

        $runner->up(dryRun: true);

        $found = false;

        foreach ($messages as $msg) {
            if (str_contains($msg, '[DRY-RUN]')) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, 'Expected [DRY-RUN] prefix in log messages');
    }

    public function testDryRunWithSteps(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        $applied = $runner->up(steps: 1, dryRun: true);

        $this->assertSame(['create_users'], $applied);
        $this->assertCount(2, $runner->getPending());
    }

    public function testDryRunDownDoesNotRevert(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'add_posts' => new AddPostsTableMigration(),
        ]);

        // Actually apply first
        $runner->up();

        // Dry-run revert
        $reverted = $runner->down(steps: 1, dryRun: true);

        $this->assertSame(['add_posts'], $reverted);

        // Both should still be applied
        $this->assertCount(2, $runner->getApplied());

        // Tables should still exist
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='posts'");
        $this->assertNotNull($row);
    }

    public function testDryRunDownIrreversibleThrows(): void
    {
        $runner = $this->createRunner([
            'create_users' => new CreateUsersTableMigration(),
            'irreversible' => new IrreversibleMigration(),
        ]);

        $runner->up();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('cannot be reverted');

        $runner->down(steps: 1, dryRun: true);
    }

    public function testDryRunEventsContainDryRunFlag(): void
    {
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$events) {
                $events[] = $event;

                return $event;
            });

        $runner = $this->createRunner(
            ['create_users' => new CreateUsersTableMigration()],
            eventDispatcher: $dispatcher,
        );

        $runner->up(dryRun: true);

        $this->assertCount(2, $events);
        $this->assertInstanceOf(MigrationBeforeEvent::class, $events[0]);
        $this->assertTrue($events[0]->isDryRun());
        $this->assertInstanceOf(MigrationAfterEvent::class, $events[1]);
        $this->assertTrue($events[1]->isDryRun());
    }
}
