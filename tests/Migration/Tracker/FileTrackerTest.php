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

use Hector\Migration\Exception\MigrationException;
use Hector\Migration\Tracker\FileTracker;
use PHPUnit\Framework\TestCase;

class FileTrackerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'hector_migration_test_') . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testEmptyTracker(): void
    {
        $tracker = new FileTracker($this->tempFile);

        $this->assertCount(0, $tracker);
        $this->assertEmpty($tracker->getArrayCopy());
        $this->assertFalse($tracker->isApplied('any'));
    }

    public function testMarkApplied(): void
    {
        $tracker = new FileTracker($this->tempFile);

        $tracker->markApplied('migration_1');

        $this->assertTrue($tracker->isApplied('migration_1'));
        $this->assertFalse($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testMarkAppliedIdempotent(): void
    {
        $tracker = new FileTracker($this->tempFile);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_1');

        $this->assertCount(1, $tracker);
    }

    public function testMarkReverted(): void
    {
        $tracker = new FileTracker($this->tempFile);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_2');
        $tracker->markReverted('migration_1');

        $this->assertFalse($tracker->isApplied('migration_1'));
        $this->assertTrue($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testPersistence(): void
    {
        $tracker1 = new FileTracker($this->tempFile);
        $tracker1->markApplied('migration_1');
        $tracker1->markApplied('migration_2');

        // New instance reads from the same file
        $tracker2 = new FileTracker($this->tempFile);

        $this->assertTrue($tracker2->isApplied('migration_1'));
        $this->assertTrue($tracker2->isApplied('migration_2'));
        $this->assertCount(2, $tracker2);
    }

    public function testIteratorAggregate(): void
    {
        $tracker = new FileTracker($this->tempFile);
        $tracker->markApplied('m1');
        $tracker->markApplied('m2');

        $ids = [];
        foreach ($tracker as $id) {
            $ids[] = $id;
        }

        $this->assertSame(['m1', 'm2'], $ids);
    }

    public function testCorruptedJsonThrows(): void
    {
        file_put_contents($this->tempFile, '{invalid json!!!');

        $tracker = new FileTracker($this->tempFile);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $tracker->getArrayCopy();
    }
}
