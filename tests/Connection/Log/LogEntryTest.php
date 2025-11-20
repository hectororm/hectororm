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

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Connection;
use Hector\Connection\Log\LogEntry;
use PHPUnit\Framework\TestCase;

class LogEntryTest extends TestCase
{
    public function testConstruct(): void
    {
        $logEntry = new LogEntry(
            $connection = Connection::DEFAULT_NAME,
            $statement = 'STATEMENT',
            $parameters = new BindParamList(['foo' => 'bar'])
        );

        $this->assertInstanceOf(LogEntry::class, $logEntry);
        $this->assertEquals($connection, $logEntry->getConnection());
        $this->assertEquals($statement, $logEntry->getStatement());
        $this->assertEquals($parameters, $logEntry->getParameters());
    }

    public function testEnd(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');

        $this->assertNull($logEntry->getDuration());

        $logEntry->end();

        $this->assertGreaterThan(0, $logEntry->getDuration());
    }

    public function testGetStart(): void
    {
        $time1 = microtime(true);
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');
        usleep(200); // force wait for very fast processor :)
        $time2 = microtime(true);

        $this->assertGreaterThan($time1, $logEntry->getStart());
        $this->assertIsFloat($logEntry->getStart());
        $this->assertLessThan($time2, $logEntry->getStart());
    }

    public function testGetEnd(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');

        $this->assertNull($logEntry->getEnd());

        $time1 = microtime(true);
        usleep(100); // force wait for very fast processor :)
        $logEntry->end();
        $time2 = microtime(true);

        $this->assertIsFloat($logEntry->getEnd());
        $this->assertGreaterThan($time1, $logEntry->getEnd());
        $this->assertLessThan($time2, $logEntry->getEnd());
    }

    public function testGetDuration(): void
    {
        $time1 = microtime(true);
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');

        $this->assertNull($logEntry->getDuration());

        $logEntry->end();
        $time2 = microtime(true);

        $this->assertGreaterThan(0, $logEntry->getDuration());
        $this->assertLessThan($time2 - $time1, $logEntry->getDuration());
    }

    public function testGetConnection(): void
    {
        $logEntry = new LogEntry($connection = 'FooConnection', 'STATEMENT');

        $this->assertEquals($connection, $logEntry->getConnection());
    }

    public function testGetStatement(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, $statement = 'STATEMENT');

        $this->assertEquals($statement, $logEntry->getStatement());
    }

    public function testGetParameters(): void
    {
        $logEntry = new LogEntry(
            Connection::DEFAULT_NAME,
            'STATEMENT',
            $parameters = new BindParamList(['foo' => 'bar', 'qux' => 'quux'])
        );

        $this->assertEquals($parameters, $logEntry->getParameters());
    }

    public function testGetParametersDefaultValue(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');

        $this->assertIsArray($logEntry->getParameters());
        $this->assertEmpty($logEntry->getParameters());
    }

    public function testGetTrace(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT', trace: $trace = 'foo');

        $this->assertEquals($trace, $logEntry->getTrace());
    }

    public function testGetTraceDefaultValue(): void
    {
        $logEntry = new LogEntry(Connection::DEFAULT_NAME, 'STATEMENT');

        $this->assertNull($logEntry->getTrace());
    }
}
