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

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Hector\Connection\Connection;
use Hector\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;

class DateTimeBindTest extends TestCase
{
    private const TABLE = 'hector_query_datetime_bind_test';

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

        $this->connection = new Connection($dsn, timezone: new DateTimeZone('UTC'));
        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (id INTEGER NOT NULL PRIMARY KEY, created_at DATETIME NULL)'
        );
        $this->connection->execute(
            'INSERT INTO ' . self::TABLE . ' (id, created_at) VALUES ' .
            "(1, '2026-01-01 00:00:00'), (2, '2026-01-02 00:00:00'), (3, '2026-01-03 00:00:00')"
        );

        return $this->connection;
    }

    private function builder(Connection $connection): QueryBuilder
    {
        return (new QueryBuilder($connection))->from(self::TABLE);
    }

    private function ids(QueryBuilder $builder): array
    {
        return array_map(
            'intval',
            iterator_to_array($builder->column('id')->orderBy('id')->fetchColumn(), false)
        );
    }

    private function createdAt(Connection $connection, int $id): ?string
    {
        return $connection->fetchOne('SELECT created_at FROM ' . self::TABLE . ' WHERE id = ?', [$id])['created_at'];
    }

    /**
     * @dataProvider driverProvider
     */
    public function testWhere(string $driver): void
    {
        $connection = $this->getConnection($driver);

        // 2026-01-02 00:00:00 in the storage timezone (UTC)
        $date = new DateTimeImmutable('2026-01-02 09:00:00+09:00');

        $this->assertSame([1], $this->ids($this->builder($connection)->where('created_at', '<', $date)));
        $this->assertSame([2], $this->ids($this->builder($connection)->where('created_at', $date)));
        $this->assertSame([2, 3], $this->ids($this->builder($connection)->where('created_at', '>=', $date)));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testWhereBetween(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $builder = $this->builder($connection)->whereBetween(
            'created_at',
            new DateTimeImmutable('2026-01-01 12:00:00+00:00'),
            new DateTime('2026-01-03 00:00:00+00:00'),
        );

        $this->assertSame([2, 3], $this->ids($builder));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testWhereIn(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $builder = $this->builder($connection)->whereIn(
            'created_at',
            [
                new DateTimeImmutable('2026-01-01 00:00:00+00:00'),
                new DateTimeImmutable('2026-01-03 01:00:00+01:00'),
            ]
        );

        $this->assertSame([1, 3], $this->ids($builder));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testInsert(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->insert([
            'id' => 4,
            'created_at' => new DateTimeImmutable('2026-01-04 09:30:00+09:00'),
        ]);

        $this->assertSame(1, $affected);
        $this->assertSame('2026-01-04 00:30:00', $this->createdAt($connection, 4));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testUpdate(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)
            ->where('created_at', new DateTimeImmutable('2026-01-01 00:00:00+00:00'))
            ->update(['created_at' => new DateTime('2026-02-01 09:30:00+09:00')]);

        $this->assertSame(1, $affected);
        $this->assertSame('2026-02-01 00:30:00', $this->createdAt($connection, 1));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testBuilderIsReusable(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $date = new DateTimeImmutable('2026-01-02 00:00:00+00:00');
        $builder = $this->builder($connection)->where('created_at', '>=', $date);

        $this->assertSame(2, $builder->count());
        $this->assertSame(2, $builder->count());
        $this->assertSame([2, 3], $this->ids($builder));
    }
}
