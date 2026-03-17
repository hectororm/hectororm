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
use Hector\Migration\Tracker\ChainStrategy;
use Hector\Migration\Tracker\ChainTracker;
use Hector\Migration\Tracker\FileTracker;
use PHPUnit\Framework\TestCase;

class ChainTrackerTest extends TestCase
{
    private string $tempFile1;
    private string $tempFile2;

    protected function setUp(): void
    {
        $this->tempFile1 = tempnam(sys_get_temp_dir(), 'hector_chain1_') . '.json';
        $this->tempFile2 = tempnam(sys_get_temp_dir(), 'hector_chain2_') . '.json';
    }

    protected function tearDown(): void
    {
        foreach ([$this->tempFile1, $this->tempFile2] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function testEmptyTrackersThrows(): void
    {
        $this->expectException(MigrationException::class);

        new ChainTracker([]);
    }

    public function testMarkAppliedPropagatesToAll(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);
        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ANY);

        $chain->markApplied('m1');

        $this->assertTrue($t1->isApplied('m1'));
        $this->assertTrue($t2->isApplied('m1'));
    }

    public function testMarkRevertedPropagatesToAll(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);
        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ANY);

        $chain->markApplied('m1');
        $chain->markReverted('m1');

        $this->assertFalse($t1->isApplied('m1'));
        $this->assertFalse($t2->isApplied('m1'));
    }

    public function testStrategyAny(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);

        // Only mark in t1
        $t1->markApplied('m1');

        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ANY);

        $this->assertTrue($chain->isApplied('m1'));
        $this->assertFalse($chain->isApplied('m2'));
        $this->assertCount(1, $chain);
    }

    public function testStrategyAll(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);

        // Only mark in t1
        $t1->markApplied('m1');

        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ALL);

        $this->assertFalse($chain->isApplied('m1'));

        // Now mark in t2 too
        $t2->markApplied('m1');
        $chain2 = new ChainTracker([$t1, $t2], ChainStrategy::ALL);

        $this->assertTrue($chain2->isApplied('m1'));
    }

    public function testStrategyFirst(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);

        $t1->markApplied('m1');
        $t2->markApplied('m2');

        $chain = new ChainTracker([$t1, $t2], ChainStrategy::FIRST);

        $this->assertTrue($chain->isApplied('m1'));
        $this->assertFalse($chain->isApplied('m2'));
        $this->assertSame(['m1'], $chain->getArrayCopy());
    }

    public function testGetArrayCopyAny(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);

        $t1->markApplied('m1');
        $t1->markApplied('m2');
        $t2->markApplied('m2');
        $t2->markApplied('m3');

        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ANY);

        $result = $chain->getArrayCopy();
        sort($result);

        $this->assertSame(['m1', 'm2', 'm3'], $result);
    }

    public function testGetArrayCopyAll(): void
    {
        $t1 = new FileTracker($this->tempFile1);
        $t2 = new FileTracker($this->tempFile2);

        $t1->markApplied('m1');
        $t1->markApplied('m2');
        $t2->markApplied('m2');
        $t2->markApplied('m3');

        $chain = new ChainTracker([$t1, $t2], ChainStrategy::ALL);

        $this->assertSame(['m2'], $chain->getArrayCopy());
    }
}
