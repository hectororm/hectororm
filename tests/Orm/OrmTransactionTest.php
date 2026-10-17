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

namespace Hector\Orm\Tests;

use Hector\Connection\Connection;
use Hector\Orm\Attributes as OrmAttribute;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\SchemaContainer;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class OrmTransactionTest extends TestCase
{
    private Connection $connection;
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $property->setAccessible(true);
        $this->previousReflections = $property->getValue();
        $property->setValue(null, []);

        $this->connection = new Connection('sqlite::memory:');
        $this->connection->execute(
            'CREATE TABLE transaction_item (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . "code TEXT NOT NULL CHECK (code <> 'invalid'))",
        );
        $schemas = new SchemaContainer([...(new Sqlite($this->connection))->generateSchemas('main')]);
        $this->orm = new Orm($this->connection, $schemas);
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $property = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        PHP_VERSION_ID < 80100 && $property->setAccessible(true);
        $property->setValue(null, $this->previousReflections);
    }

    private function codes(): array
    {
        return $this->connection->fetchColumn('SELECT code FROM transaction_item ORDER BY id');
    }

    private function item(string $code): TransactionItem
    {
        $item = new TransactionItem();
        $item->code = $code;

        return $item;
    }

    public function testTransactionCommitsAndReturnsResult(): void
    {
        $result = $this->orm->transaction(function (Orm $orm): string {
            $this->assertSame($this->orm, $orm);
            $orm->save($this->item('a'), true);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(['a'], $this->codes());
    }

    public function testTransactionRollsBackAndRethrows(): void
    {
        $exception = new RuntimeException('Failure');

        try {
            $this->orm->transaction(function (Orm $orm) use ($exception): void {
                $orm->save($this->item('a'), true);

                throw $exception;
            });
            $this->fail('Exception expected');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame([], $this->codes());
    }

    public function testTransactionRetriesWrappedRetryableFailure(): void
    {
        $calls = 0;

        $this->orm->transaction(function (Orm $orm) use (&$calls): void {
            $calls++;
            $orm->save($this->item('attempt' . $calls), true);
            if (1 === $calls) {
                $busy = new PDOException('SQLSTATE[HY000]: General error: 5 database is locked');
                $busy->errorInfo = ['HY000', 5, 'database is locked'];

                throw new OrmException('Error while persisting entities', previous: $busy);
            }
        }, attempts: 2);

        $this->assertSame(2, $calls);
        $this->assertSame(['attempt2'], $this->codes());
    }

    public function testFailedPersistOnlyRollsBackItsOwnWrites(): void
    {
        $this->orm->transaction(function (Orm $orm): void {
            $orm->save($this->item('outer'), true);

            $orm->save($this->item('batch'));
            $orm->save($this->item('invalid'));
            try {
                $orm->persist();
                $this->fail('Persist must fail');
            } catch (OrmException) {
            }

            $this->assertTrue($this->connection->inTransaction());
            $this->connection->execute("INSERT INTO transaction_item (code) VALUES ('after')");
        });

        $this->assertFalse($this->connection->inTransaction());
        $this->assertSame(['outer', 'after'], $this->codes());
    }
}

#[OrmAttribute\Table('transaction_item', 'main')]
class TransactionItem extends MagicEntity
{
}
