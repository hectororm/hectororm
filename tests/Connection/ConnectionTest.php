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

namespace Hector\Connection\Tests;

use Generator;
use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ConnectionTest extends TestCase
{
    public function testFromPdo(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $readPdo = new PDO('sqlite::memory:');
        $connection = Connection::fromPdo($pdo, $readPdo);

        $this->assertSame($pdo, $connection->getPdo());
        $this->assertSame($readPdo, $connection->getReadPdo());
    }

    public function testSerialization(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection2 = unserialize(serialize($connection));

        $this->assertEquals($connection->__serialize(), $connection2->__serialize());
    }

    public function testConstructBadDSN(): void
    {
        $connection = new Connection('fake::memory:');

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testGetDefaultName(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertEquals(Connection::DEFAULT_NAME, $connection->getName());
    }

    public function testGetName(): void
    {
        $connection = new Connection('sqlite::memory:', name: 'connection');

        $this->assertEquals('connection', $connection->getName());
    }

    public function testGetLogger(): void
    {
        $connection = new Connection('sqlite::memory:', logger: new Logger());

        $this->assertInstanceOf(Logger::class, $connection->getLogger());
        $this->assertSame($connection->getLogger(), $connection->getLogger());
    }

    public function testGetLoggerNull(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertNull($connection->getLogger());
        $this->assertSame($connection->getLogger(), $connection->getLogger());
    }

    public function testGetPdo(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertInstanceOf(PDO::class, $connection->getPdo());
        $this->assertSame($connection->getPdo(), $connection->getPdo());
    }

    public function testGetReadPdo(): void
    {
        $connection = new Connection('sqlite::memory:', readDsn: 'sqlite::memory:');

        $this->assertInstanceOf(PDO::class, $connection->getReadPdo());
        $this->assertNotSame($connection->getPdo(), $connection->getReadPdo());
        $this->assertSame($connection->getReadPdo(), $connection->getReadPdo());
    }

    public function testGetReadPdoNotDefined(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertInstanceOf(PDO::class, $connection->getReadPdo());
        $this->assertSame($connection->getPdo(), $connection->getReadPdo());
    }

    public function testGetReadPdoTransactionStarted(): void
    {
        $connection = new Connection('sqlite::memory:', readDsn: 'sqlite::memory:');

        $this->assertNotSame($connection->getPdo(), $connection->getReadPdo());

        $connection->beginTransaction();

        $this->assertSame($connection->getPdo(), $connection->getReadPdo());
    }

    public function testGetDriverName(): void
    {
        $connection = new Connection('sqlite::memory:', 'sqlite::memory:');

        $this->assertEquals('sqlite', $connection->getDriverName());
    }

    public function testGetDriverInfo(): void
    {
        $connection = new Connection('sqlite::memory:', 'sqlite::memory:');

        $this->assertEquals('sqlite', $connection->getDriverInfo()->getDriver());
    }

    public function testGetLastInsertId(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->execute(
            'CREATE TABLE "table"
(
	table_id integer not null
		constraint table_pk
			primary key autoincrement,
	table_col varchar
)'
        );

        $connection->execute('INSERT INTO `table` (`table_col`) VALUES ("Foo");');

        $this->assertEquals(1, $connection->getLastInsertId());
    }

    public function testBeginTransaction(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();

        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $this->assertTrue($connection->inTransaction());
        $this->assertEquals(1, $reflectionProperty->getValue($connection));
    }

    public function testBeginTransactionAnother(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();
        $connection->beginTransaction();

        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $this->assertTrue($connection->inTransaction());
        $this->assertEquals(2, $reflectionProperty->getValue($connection));

        $connection->rollBack();
    }

    public function testCommit(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();

        $this->assertEquals(1, $reflectionProperty->getValue($connection));

        $connection->commit();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testCommitWithNoTransaction(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');

        $this->assertEquals(0, $reflectionProperty->getValue($connection));

        $connection->commit();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testCommitWithMultipleTransactions(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();
        $connection->beginTransaction();

        $this->assertEquals(2, $reflectionProperty->getValue($connection));

        $connection->commit();

        $this->assertEquals(1, $reflectionProperty->getValue($connection));

        $connection->commit();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testRollBack(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();

        $this->assertEquals(1, $reflectionProperty->getValue($connection));

        $connection->rollBack();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testRollBackNoTransactions(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');

        $this->assertEquals(0, $reflectionProperty->getValue($connection));

        $connection->rollBack();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testRollBackWithMultipleTransactions(): void
    {
        $reflectionProperty = new ReflectionProperty(Connection::class, 'transactions');
        $reflectionProperty->setAccessible(true);

        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();
        $connection->beginTransaction();

        $this->assertEquals(2, $reflectionProperty->getValue($connection));

        $connection->rollBack();

        $this->assertEquals(0, $reflectionProperty->getValue($connection));
    }

    public function testInTransaction(): void
    {
        $connection = new Connection('sqlite::memory:');
        $connection->beginTransaction();

        $this->assertTrue($connection->inTransaction());
    }

    public function testInTransactionFalse(): void
    {
        $connection = new Connection('sqlite::memory:');

        $this->assertFalse($connection->inTransaction());
    }

    public function testExecute(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->execute('SELECT * FROM `table`;');

        $this->assertEquals(0, $result);
    }

    public function testExecuteWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->execute('SELECT * FROM `table` WHERE `table_id` = ?;', [2]);

        $this->assertEquals(0, $result);
    }

    public function testFetchAll(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchAll('SELECT * FROM `table` LIMIT 2;');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testFetchAllWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchAll('SELECT * FROM `table` WHERE `table_id` = :p;', ['p' => 2]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testFetchAllWithBindParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $var = 2;
        $result = $connection->fetchAll('SELECT * FROM `table` WHERE `table_id` = :_h_0;', [$var]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testYieldAll(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $iterator = $connection->yieldAll('SELECT * FROM `table` LIMIT 2;');

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertCount(2, iterator_to_array($iterator));
    }

    public function testYieldAllWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $iterator = $connection->yieldAll('SELECT * FROM `table` WHERE `table_id` = :p;', ['p' => 2]);

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertCount(1, iterator_to_array($iterator));
    }

    public function testYieldAllWithBindParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $var = 2;
        $iterator = $connection->yieldAll('SELECT * FROM `table` WHERE `table_id` = :_h_0;', [$var]);

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertCount(1, iterator_to_array($iterator));
    }

    public function testFetchOne(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));

        $result = $connection->fetchOne('SELECT * FROM `table`;');

        $this->assertNotNull($result);
        $this->assertEquals('Foo', $result['table_col']);
    }

    public function testFetchOneWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchOne('SELECT * FROM `table` WHERE `table_id` = :_h_0;', [2]);

        $this->assertNotNull($result);
        $this->assertEquals('Bar', $result['table_col']);
    }

    public function testFetchOneWithNamedParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchOne('SELECT * FROM `table` WHERE `table_id` = :id;', ['id' => 2]);

        $this->assertNotNull($result);
        $this->assertEquals('Bar', $result['table_col']);
    }

    public function testFetchColumn(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchColumn('SELECT * FROM `table`;', [], 1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Foo', $result[0]);
        $this->assertEquals('Bar', $result[1]);
    }

    public function testYieldColumn(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $iterator = $connection->yieldColumn('SELECT * FROM `table`;', [], 1);

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertCount(2, $result = iterator_to_array($iterator));
        $this->assertNotNull($result);
        $this->assertEquals('Foo', $result[0]);
        $this->assertEquals('Bar', $result[1]);
    }

    public function testFetchColumnWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $result = $connection->fetchColumn('SELECT * FROM `table` WHERE `table_id` = ?;', [2], 1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Bar', $result[0]);
    }

    public function testYieldColumnWithParam(): void
    {
        $connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
        $iterator = $connection->yieldColumn('SELECT * FROM `table` WHERE `table_id` = ?;', [2], 1);

        $this->assertInstanceOf(Generator::class, $iterator);
        $this->assertCount(1, $result = iterator_to_array($iterator));
        $this->assertNotNull($result);
        $this->assertEquals('Bar', $result[0]);
    }
}
