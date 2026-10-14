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

namespace Hector\Schema\Tests\Generator;

use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverInfo;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Generator\AbstractGenerator;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class MySQLChecksTest extends TestCase
{
    public static function providerUnsupportedCatalogs(): array
    {
        return [
            ['mysql', '5.7.44'],
            ['mysql', '8.0.15'],
            ['mariadb', '10.2.0'],
            ['vitess', '8.0.30'],
            ['unknown', '99.0.0'],
            ['pgsql', '17.0'],
        ];
    }

    /**
     * @dataProvider providerUnsupportedCatalogs
     */
    public function testUnsupportedServersAreNotQueriedForCheckMetadata(string $driver, string $version): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo($driver, $version));
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->never())->method('fetchAll');

        self::assertNull((new FakeMySQL($connection))->getChecksInfo('sakila', 'items'));
    }

    public function testMySqlPreservesEnforcementStateAndVerbatimExpressions(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo('mysql', '8.0.16'));
        $connection->expects($this->never())->method('fetchOne');
        $connection->expects($this->once())->method('fetchAll')->with(
            $this->callback(static fn(string $sql): bool =>
                str_contains($sql, 't.`CONSTRAINT_SCHEMA` = c.`CONSTRAINT_SCHEMA`') &&
                str_contains($sql, 't.`CONSTRAINT_NAME` = c.`CONSTRAINT_NAME`') &&
                str_contains($sql, 't.`TABLE_SCHEMA` = :schema') &&
                str_contains($sql, 't.`TABLE_NAME` = :table') &&
                str_contains($sql, 't.`ENFORCED`')),
            ['schema' => 'sakila', 'table' => 'items'],
        )->willReturn([
            ['CONSTRAINT_NAME' => 'active', 'CHECK_CLAUSE' => ' (`price` >= 0) ', 'ENFORCED' => 'YES'],
            ['CONSTRAINT_NAME' => 'inactive', 'CHECK_CLAUSE' => '0', 'ENFORCED' => 'NO'],
            ['CONSTRAINT_NAME' => 'unknown', 'CHECK_CLAUSE' => 'price < 100', 'ENFORCED' => null],
        ]);

        self::assertSame([
            ['name' => 'active', 'expression' => ' (`price` >= 0) ', 'enforced' => true],
            ['name' => 'inactive', 'expression' => '0', 'enforced' => false],
            ['name' => 'unknown', 'expression' => 'price < 100', 'enforced' => null],
        ], (new FakeMySQL($connection))->getChecksInfo('sakila', 'items'));
    }

    public function testMariaDbCatalogAvailabilityIsCheckedAndCached(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo('mariadb', '10.2.22'));
        $connection->expects($this->once())->method('fetchOne')->with(
            $this->stringContains("`TABLE_NAME` = 'CHECK_CONSTRAINTS'"),
        )->willReturn(['TABLE_NAME' => 'CHECK_CONSTRAINTS']);
        $connection->expects($this->exactly(2))->method('fetchAll')->with(
            $this->callback(static fn(string $sql): bool =>
                str_contains($sql, 'c.`CONSTRAINT_SCHEMA` = :schema') &&
                str_contains($sql, 'c.`TABLE_NAME` = :table') &&
                false === str_contains($sql, 't.`ENFORCED`')),
            ['schema' => 'sakila', 'table' => 'items'],
        )->willReturn([
            ['CONSTRAINT_NAME' => 'price', 'CHECK_CLAUSE' => '`price` >= 0'],
        ]);
        $generator = new FakeMySQL($connection);
        $expected = [['name' => 'price', 'expression' => '`price` >= 0', 'enforced' => true]];

        self::assertSame($expected, $generator->getChecksInfo('sakila', 'items'));
        self::assertSame($expected, $generator->getChecksInfo('sakila', 'items'));
    }

    public function testOldMariaDbEnforcesChecksButCannotProvideCatalogMetadata(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo('mariadb', '10.2.1'));
        $connection->expects($this->once())->method('fetchOne')->willReturn(null);
        $connection->expects($this->never())->method('fetchAll');
        $generator = new FakeMySQL($connection);

        self::assertNull($generator->getChecksInfo('sakila', 'items'));
        self::assertNull($generator->getChecksInfo('sakila', 'other_items'));
    }

    public function testMissingExpressionIsNotSilentlyDiscarded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDriverInfo')->willReturn(new DriverInfo('mysql', '8.4.0'));
        $connection->method('fetchAll')->willReturn([
            ['CONSTRAINT_NAME' => 'chk_price', 'CHECK_CLAUSE' => null, 'ENFORCED' => 'YES'],
        ]);

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Missing CHECK expression for table "sakila.items"');
        (new FakeMySQL($connection))->getChecksInfo('sakila', 'items');
    }

    public function testCustomGeneratorWithoutCheckSupportRetainsUnknownMetadata(): void
    {
        $generator = $this->getMockForAbstractClass(AbstractGenerator::class, [$this->createMock(Connection::class)]);
        $generator->method('getSchemaInfo')->willReturn(['name' => 'main', 'charset' => 'utf8']);
        $generator->method('getTablesInfo')->willReturn([
            ['schema_name' => 'main', 'name' => 'items', 'type' => Table::TYPE_TABLE],
        ]);
        $generator->method('getColumnsInfo')->willReturn([]);
        $generator->method('getIndexesInfo')->willReturn([]);
        $generator->method('getForeignKeysInfo')->willReturn([]);

        self::assertFalse($generator->generateSchema('main')->getTable('items')->hasCheckMetadata());
    }
}
