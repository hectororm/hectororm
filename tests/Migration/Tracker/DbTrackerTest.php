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

namespace Hector\Migration\Tests\Tracker;

use Hector\Connection\Connection;
use Hector\Migration\Tracker\DbTracker;
use PHPUnit\Framework\TestCase;

class DbTrackerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = new Connection('sqlite::memory:');
    }

    public function testEmptyTracker(): void
    {
        $tracker = new DbTracker($this->connection);

        $this->assertCount(0, $tracker);
        $this->assertEmpty($tracker->getArrayCopy());
        $this->assertFalse($tracker->isApplied('any'));
    }

    public function testMarkApplied(): void
    {
        $tracker = new DbTracker($this->connection);

        $tracker->markApplied('migration_1');

        $this->assertTrue($tracker->isApplied('migration_1'));
        $this->assertFalse($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testMarkReverted(): void
    {
        $tracker = new DbTracker($this->connection);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_2');
        $tracker->markReverted('migration_1');

        $this->assertFalse($tracker->isApplied('migration_1'));
        $this->assertTrue($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testCustomTableName(): void
    {
        $tracker = new DbTracker($this->connection, 'my_migrations');

        $tracker->markApplied('m1');
        $this->assertTrue($tracker->isApplied('m1'));

        // Verify the default table was NOT used
        $row = $this->connection->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name='my_migrations'");
        $this->assertNotNull($row);
    }

    public function testIteratorAggregate(): void
    {
        $tracker = new DbTracker($this->connection);
        $tracker->markApplied('m1');
        $tracker->markApplied('m2');

        $ids = [];
        foreach ($tracker as $id) {
            $ids[] = $id;
        }

        $this->assertSame(['m1', 'm2'], $ids);
    }

    public function testMarkAppliedIdempotent(): void
    {
        $tracker = new DbTracker($this->connection);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_1');

        $this->assertCount(1, $tracker);
    }

    public function testPersistenceAcrossInstances(): void
    {
        $tracker1 = new DbTracker($this->connection);
        $tracker1->markApplied('m1');

        // Same connection, new tracker instance
        $tracker2 = new DbTracker($this->connection);
        $this->assertTrue($tracker2->isApplied('m1'));
    }
}
