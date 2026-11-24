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
use PHPUnit\Framework\TestCase;

/**
 * Executes upserts on SQLite, on MySQL/MariaDB (MYSQL_DSN) and, when PGSQL_DSN is set, on PostgreSQL.
 */
class UpsertExecuteTest extends TestCase
{
    private const TABLE = 'hector_upsert_test';
    private const COMPOSITE_TABLE = 'hector_upsert_composite_test';

    private ?Connection $connection = null;

    protected function tearDown(): void
    {
        $this->connection?->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection?->execute('DROP TABLE IF EXISTS ' . self::COMPOSITE_TABLE);
        $this->connection = null;
    }

    public static function driverProvider(): array
    {
        return [
            'sqlite' => ['sqlite'],
            'mysql' => ['mysql'],
            'pgsql' => ['pgsql'],
        ];
    }

    private function getConnection(string $driver): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('sqlite' !== $driver) {
            $variable = 'mysql' === $driver ? 'MYSQL_DSN' : 'PGSQL_DSN';
            $dsn = getenv($variable);
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped($variable . ' is required');
            }
        }

        $this->connection = new Connection($dsn);
        $quote = $this->connection->getDriverInfo()->getIdentifierQuote();

        $this->connection->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::TABLE . ' (' .
            'code VARCHAR(50) NOT NULL PRIMARY KEY, ' .
            'name VARCHAR(100) NOT NULL, ' .
            'note VARCHAR(100) NULL, ' .
            $quote . 'order' . $quote . ' INTEGER NOT NULL DEFAULT 0' .
            ')'
        );
        $this->connection->execute(
            'INSERT INTO ' . self::TABLE . ' (code, name, note, ' . $quote . 'order' . $quote . ') VALUES ' .
            "('A', 'First', 'keep', 1)"
        );

        $this->connection->execute('DROP TABLE IF EXISTS ' . self::COMPOSITE_TABLE);
        $this->connection->execute(
            'CREATE TABLE ' . self::COMPOSITE_TABLE . ' (' .
            'tenant INTEGER NOT NULL, ' .
            'code VARCHAR(50) NOT NULL, ' .
            'name VARCHAR(100) NOT NULL, ' .
            'PRIMARY KEY (tenant, code)' .
            ')'
        );
        $this->connection->execute(
            'INSERT INTO ' . self::COMPOSITE_TABLE . " (tenant, code, name) VALUES (1, 'A', 'First')"
        );

        return $this->connection;
    }

    private function builder(Connection $connection, string $table = self::TABLE): QueryBuilder
    {
        return (new QueryBuilder($connection))->from($table);
    }

    /**
     * MySQL/MariaDB count 1 for an inserted row, 2 for an updated one and 0 for an unchanged one;
     * SQLite and PostgreSQL count 1 for every inserted or updated row.
     */
    private function isMySQLFamily(Connection $connection): bool
    {
        return in_array($connection->getDriverInfo()->getDriver(), ['mysql', 'mariadb'], true);
    }

    private function rows(Connection $connection): array
    {
        $quote = $connection->getDriverInfo()->getIdentifierQuote();
        $rows = [];

        $result = $connection->fetchAll(
            'SELECT code, name, note, ' . $quote . 'order' . $quote . ' FROM ' . self::TABLE . ' ORDER BY code'
        );
        foreach ($result as $row) {
            $rows[$row['code']] = [$row['name'], $row['note'], (int)$row['order']];
        }

        return $rows;
    }

    /**
     * @dataProvider driverProvider
     */
    public function testInsertsMissingRows(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->upsert(
            [
                ['code' => 'B', 'name' => 'Second', 'note' => null, 'order' => 2],
                ['code' => 'C', 'name' => 'Third', 'note' => 'n3', 'order' => 3],
            ],
            uniqueBy: ['code'],
            update: ['name'],
        );

        $this->assertSame(2, $affected);
        $this->assertSame(
            [
                'A' => ['First', 'keep', 1],
                'B' => ['Second', null, 2],
                'C' => ['Third', 'n3', 3],
            ],
            $this->rows($connection)
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testUpdatesExistingRowsAndInsertsMissingOnes(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->upsert(
            [
                ['code' => 'A', 'name' => 'Renamed', 'note' => 'overwritten', 'order' => 10],
                ['code' => 'B', 'name' => 'Second', 'note' => 'n2', 'order' => 2],
            ],
            uniqueBy: ['code'],
        );

        $this->assertSame($this->isMySQLFamily($connection) ? 3 : 2, $affected);
        $this->assertSame(
            [
                'A' => ['Renamed', 'overwritten', 10],
                'B' => ['Second', 'n2', 2],
            ],
            $this->rows($connection)
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testColumnsOutsideUpdateListRemainUnchanged(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $this->builder($connection)->upsert(
            [
                ['code' => 'A', 'name' => 'Renamed', 'note' => 'overwritten', 'order' => 10],
                ['code' => 'B', 'name' => 'Second', 'note' => 'n2', 'order' => 2],
            ],
            uniqueBy: ['code'],
            update: ['name'],
        );

        $this->assertSame(
            [
                'A' => ['Renamed', 'keep', 1],
                'B' => ['Second', 'n2', 2],
            ],
            $this->rows($connection)
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testUpdatesQuotedReservedColumn(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $this->builder($connection)->upsert(
            [['code' => 'A', 'name' => 'ignored', 'note' => 'ignored', 'order' => 42]],
            uniqueBy: ['code'],
            update: ['order'],
        );

        $this->assertSame(['A' => ['First', 'keep', 42]], $this->rows($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testEmptyUpdateListLeavesExistingRowsUntouched(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->upsert(
            [
                ['code' => 'A', 'name' => 'Renamed', 'note' => 'overwritten', 'order' => 10],
                ['code' => 'B', 'name' => 'Second', 'note' => 'n2', 'order' => 2],
            ],
            uniqueBy: ['code'],
            update: [],
        );

        $this->assertSame(1, $affected);
        $this->assertSame(
            [
                'A' => ['First', 'keep', 1],
                'B' => ['Second', 'n2', 2],
            ],
            $this->rows($connection)
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testAffectedRowsOfAnUnchangedRowAreDriverSpecific(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $affected = $this->builder($connection)->upsert(
            [['code' => 'A', 'name' => 'First', 'note' => 'keep', 'order' => 1]],
            uniqueBy: ['code'],
        );

        $this->assertSame($this->isMySQLFamily($connection) ? 0 : 1, $affected);
        $this->assertSame(['A' => ['First', 'keep', 1]], $this->rows($connection));
    }

    /**
     * @dataProvider driverProvider
     */
    public function testCompositeConflictKey(string $driver): void
    {
        $connection = $this->getConnection($driver);

        $this->builder($connection, self::COMPOSITE_TABLE)->upsert(
            [
                ['tenant' => 1, 'code' => 'A', 'name' => 'Renamed'],
                ['tenant' => 2, 'code' => 'A', 'name' => 'Other tenant'],
                ['tenant' => 1, 'code' => 'B', 'name' => 'Other code'],
            ],
            uniqueBy: ['tenant', 'code'],
            update: ['name'],
        );

        $rows = [];
        $result = $connection->fetchAll(
            'SELECT tenant, code, name FROM ' . self::COMPOSITE_TABLE . ' ORDER BY tenant, code'
        );
        foreach ($result as $row) {
            $rows[] = [(int)$row['tenant'], $row['code'], $row['name']];
        }

        $this->assertSame(
            [
                [1, 'A', 'Renamed'],
                [1, 'B', 'Other code'],
                [2, 'A', 'Other tenant'],
            ],
            $rows
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testValuesAreBound(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $name = "Robert'); DROP TABLE " . self::TABLE . '; -- `"';

        $this->builder($connection)->upsert(
            [
                ['code' => 'A', 'name' => $name, 'note' => null, 'order' => 1],
                ['code' => "B'", 'name' => $name, 'note' => ':_h_0', 'order' => 2],
            ],
            uniqueBy: ['code'],
        );

        $this->assertSame(
            [
                'A' => [$name, null, 1],
                "B'" => [$name, ':_h_0', 2],
            ],
            $this->rows($connection)
        );
    }

    /**
     * @dataProvider driverProvider
     */
    public function testBuilderReuse(string $driver): void
    {
        $connection = $this->getConnection($driver);
        $builder = $this->builder($connection);

        $builder->upsert([['code' => 'B', 'name' => 'Second', 'note' => null, 'order' => 2]], uniqueBy: ['code']);
        $builder->upsert([['code' => 'B', 'name' => 'Renamed', 'note' => null, 'order' => 2]], uniqueBy: ['code']);

        $this->assertSame(
            [
                'A' => ['First', 'keep', 1],
                'B' => ['Renamed', null, 2],
            ],
            $this->rows($connection)
        );
        $this->assertSame(2, $builder->count());
    }
}
