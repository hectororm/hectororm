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
use Hector\Migration\Tracker\FlysystemTracker;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class FlysystemTrackerTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testEmptyTracker(): void
    {
        $tracker = new FlysystemTracker($this->filesystem);

        $this->assertCount(0, $tracker);
        $this->assertEmpty($tracker->getArrayCopy());
        $this->assertFalse($tracker->isApplied('any'));
    }

    public function testMarkApplied(): void
    {
        $tracker = new FlysystemTracker($this->filesystem);

        $tracker->markApplied('migration_1');

        $this->assertTrue($tracker->isApplied('migration_1'));
        $this->assertFalse($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testMarkAppliedIdempotent(): void
    {
        $tracker = new FlysystemTracker($this->filesystem);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_1');

        $this->assertCount(1, $tracker);
    }

    public function testMarkReverted(): void
    {
        $tracker = new FlysystemTracker($this->filesystem);

        $tracker->markApplied('migration_1');
        $tracker->markApplied('migration_2');
        $tracker->markReverted('migration_1');

        $this->assertFalse($tracker->isApplied('migration_1'));
        $this->assertTrue($tracker->isApplied('migration_2'));
        $this->assertCount(1, $tracker);
    }

    public function testPersistenceAcrossInstances(): void
    {
        $tracker1 = new FlysystemTracker($this->filesystem);
        $tracker1->markApplied('migration_1');
        $tracker1->markApplied('migration_2');

        // New instance, same filesystem
        $tracker2 = new FlysystemTracker($this->filesystem);

        $this->assertTrue($tracker2->isApplied('migration_1'));
        $this->assertTrue($tracker2->isApplied('migration_2'));
        $this->assertCount(2, $tracker2);
    }

    public function testIteratorAggregate(): void
    {
        $tracker = new FlysystemTracker($this->filesystem);
        $tracker->markApplied('m1');
        $tracker->markApplied('m2');

        $ids = [];
        foreach ($tracker as $id) {
            $ids[] = $id;
        }

        $this->assertSame(['m1', 'm2'], $ids);
    }

    public function testCustomFilePath(): void
    {
        $tracker = new FlysystemTracker($this->filesystem, 'custom/path/tracker.json');

        $tracker->markApplied('m1');
        $this->assertTrue($tracker->isApplied('m1'));

        // Verify file was created at custom path
        $this->assertTrue($this->filesystem->fileExists('custom/path/tracker.json'));
    }

    public function testCorruptedJsonThrows(): void
    {
        $this->filesystem->write('.hector.migrations.json', '{invalid json!!!');

        $tracker = new FlysystemTracker($this->filesystem);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $tracker->getArrayCopy();
    }
}
