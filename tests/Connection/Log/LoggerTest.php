<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Connection\Tests\Log;

use Hector\Connection\Log\LogEntry;
use Hector\Connection\Log\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    public function testSerialization()
    {
        $logger = new Logger();
        $logger->newEntry('STATEMENT');
        $logger2 = unserialize(serialize($logger));

        $this->assertCount(1, $logger);
        $this->assertCount(0, $logger2);
    }

    public function testCount()
    {
        $logger = new Logger();

        $this->assertCount(0, $logger);

        $logger->newEntry('STATEMENT');
        $logger->newEntry('STATEMENT');

        $this->assertCount(2, $logger);
    }

    public function testAdd()
    {
        $logger = new Logger();
        $logEntry = new LogEntry('STATEMENT');
        $logger->add($logEntry);

        $this->assertContains($logEntry, $logger->getLogs());
    }

    public function testGetLogs()
    {
        $logger = new Logger();
        $logger->newEntry('STATEMENT');
        $logger->newEntry('STATEMENT');

        $this->assertNotEmpty($logger->getLogs());
        $this->assertCount(2, $logger->getLogs());
        $this->assertContainsOnlyInstancesOf(LogEntry::class, $logger->getLogs());
    }

    public function testGetLogsEmpty()
    {
        $logger = new Logger();

        $this->assertIsArray($logger->getLogs());
        $this->assertEmpty($logger->getLogs());
    }

    public function testNewEntry()
    {
        $logger = new Logger();

        $this->assertInstanceOf(LogEntry::class, $logEntry = $logger->newEntry('STATEMENT'));
        $this->assertContains($logEntry, $logger->getLogs());
    }
}
