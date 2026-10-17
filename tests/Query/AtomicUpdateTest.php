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

namespace Hector\Query\Tests;

use Hector\Connection\Connection;
use Hector\Query\QueryBuilder;
use Hector\Query\Statement\Decrement;
use Hector\Query\Statement\Increment;
use Hector\Query\Statement\Quoted;
use PHPUnit\Framework\TestCase;

class AtomicUpdateTest extends TestCase
{
    private const TABLE = 'hector_atomic_update_test';

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        $this->connection?->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection = null;
    }

    public static function driverProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
        ];
    }

    private function getConnection(string $driver): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required');
            }
        }

        $this->connection = new Connection($dsn);
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (' .
            'id INTEGER NOT NULL PRIMARY KEY, ' .
            'stock INTEGER NOT NULL, ' .
            'reserved INTEGER NOT NULL DEFAULT 0, ' .
            'price DECIMAL(10,2) NOT NULL DEFAULT 0, ' .
            'label VARCHAR(50) NULL' .
            ')'
        );
        $this->connection->execute(
            'INSERT INTO ' . self::TABLE . ' (id, stock, reserved, price) VALUES ' .
            '(1, 10, 0, 9.90), (2, 3, 0, 19.90), (3, 0, 0, 0.00)'
        );

        return $this->connection;
    }

    private function builder(Connection $connection): QueryBuilder
    {
        return (new QueryBuilder($connection))->from(self::TABLE);
    }

    private function row(Connection $connection, int $id): array
    {
        return $connection->fetchOne(
            'SELECT stock, reserved, price, label FROM ' . self::TABLE . ' WHERE id = ?',
            [$id]
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testIncrement(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->where('id', 1)->update(['stock' => new Increment(5)]);

        $this->assertSame(1, $affected);
        $this->assertEquals(15, $this->row($connection, 1)['stock']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testDecrement(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->where('id', 1)->update(['stock' => new Decrement()]);

        $this->assertSame(1, $affected);
        $this->assertEquals(9, $this->row($connection, 1)['stock']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testNegativeAmount(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $this->builder($connection)->where('id', 1)->update(['stock' => new Decrement(-4)]);

        $this->assertEquals(14, $this->row($connection, 1)['stock']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testConditionalDecrementWithSufficientStock(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)
            ->where('id', 2)
            ->where('stock', '>=', 3)
            ->update(['stock' => new Decrement(3), 'reserved' => new Increment(3)]);

        $this->assertSame(1, $affected);
        $row = $this->row($connection, 2);
        $this->assertEquals(0, $row['stock']);
        $this->assertEquals(3, $row['reserved']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testConditionalDecrementWithInsufficientStockAffectsNoRow(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)
            ->where('id', 2)
            ->where('stock', '>=', 4)
            ->update(['stock' => new Decrement(4), 'reserved' => new Increment(4)]);

        $this->assertSame(0, $affected);
        $row = $this->row($connection, 2);
        $this->assertEquals(3, $row['stock']);
        $this->assertEquals(0, $row['reserved']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testMultipleRowsAffected(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->where('stock', '>', 0)->update(['stock' => new Increment(1)]);

        $this->assertSame(2, $affected);
        $this->assertEquals(11, $this->row($connection, 1)['stock']);
        $this->assertEquals(4, $this->row($connection, 2)['stock']);
        $this->assertEquals(0, $this->row($connection, 3)['stock']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testDecimalPrecisionWithNumericString(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $builder = $this->builder($connection)->where('id', 1);

        for ($i = 0; $i < 10; $i++) {
            $builder->update(['price' => new Increment('0.10')]);
        }

        $this->assertSame('10.90', number_format((float)$this->row($connection, 1)['price'], 2, '.', ''));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testMixedWithFixedValueAndQuotedColumn(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)
            ->where('id', 1)
            ->update([
                [new Quoted('stock'), new Decrement(2)],
                'label' => 'reserved',
            ]);

        $this->assertSame(1, $affected);
        $row = $this->row($connection, 1);
        $this->assertEquals(8, $row['stock']);
        $this->assertSame('reserved', $row['label']);
    }

    /**
     * @dataProvider driverProvider
     */
    public function testBuilderReuse(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $builder = $this->builder($connection)->where('id', 1)->where('stock', '>=', 4);

        $this->assertSame(1, $builder->update(['stock' => new Decrement(4)]));
        $this->assertSame(1, $builder->update(['stock' => new Decrement(4)]));
        $this->assertSame(0, $builder->update(['stock' => new Decrement(4)]));

        $this->assertEquals(2, $this->row($connection, 1)['stock']);
        $this->assertFalse($builder->exists());
    }
}
