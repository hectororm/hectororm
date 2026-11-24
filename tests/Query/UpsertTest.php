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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\Select;
use Hector\Query\Statement\Increment;
use Hector\Query\Statement\Raw;
use Hector\Query\Upsert;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class UpsertTest extends TestCase
{
    private const ROWS = [
        ['external_id' => 'A', 'name' => 'First'],
        ['external_id' => 'B', 'name' => 'Second'],
    ];

    private const VALUES = ' VALUES ( :_h_0, :_h_1 ), ( :_h_2, :_h_3 )';

    private function values(BindParamList $binds): array
    {
        return array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy());
    }

    public function testGetStatementEmpty(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();

        $this->assertNull($upsert->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutRows(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows([])->uniqueBy(['external_id']);

        $this->assertNull($upsert->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutTable(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->rows(self::ROWS)->uniqueBy(['external_id']);

        $this->assertNull($upsert->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public static function driverProvider(): array
    {
        $backtick = 'INSERT INTO users ( `external_id`, `name` )' . self::VALUES;
        $doubleQuote = 'INSERT INTO users ( "external_id", "name" )' . self::VALUES;

        $values = $backtick . ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)';
        $alias = $backtick . ' AS `hector_upsert` ON DUPLICATE KEY UPDATE `name` = `hector_upsert`.`name`';
        $conflict = $doubleQuote . ' ON CONFLICT ( "external_id" ) DO UPDATE SET "name" = excluded."name"';

        return [
            'no driver (fallback)' => [null, $values],
            'mysql 5.7' => [new DriverInfo('mysql', '5.7.44'), $values],
            'mysql before row alias' => [new DriverInfo('mysql', '8.0.18'), $values],
            'mysql first row alias version' => [new DriverInfo('mysql', '8.0.19'), $alias],
            'mysql version suffix' => [new DriverInfo('mysql', '8.0.19-log'), $alias],
            'mysql 8.4' => [new DriverInfo('mysql', '8.4.0'), $alias],
            'mariadb' => [new DriverInfo('mariadb', '11.4.0'), $values],
            'pgsql' => [new DriverInfo('pgsql', '16.0'), $conflict],
            'sqlite' => [new DriverInfo('sqlite', '3.45.0'), $conflict],
        ];
    }

    /**
     * @dataProvider driverProvider
     */
    public function testGetStatementIsDriverAware(?DriverInfo $driverInfo, string $expected): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id'])->update(['name']);

        $this->assertSame($expected, $upsert->getStatement($binds, $driverInfo));
        $this->assertSame(
            ['_h_0' => 'A', '_h_1' => 'First', '_h_2' => 'B', '_h_3' => 'Second'],
            $this->values($binds)
        );
    }

    public static function unsupportedDriverProvider(): array
    {
        return [
            'sqlite before upsert' => [new DriverInfo('sqlite', '3.23.1')],
            'pgsql before upsert' => [new DriverInfo('pgsql', '9.4.26')],
            'vitess' => [new DriverInfo('vitess', '8.0.30')],
            'unknown engine' => [new DriverInfo('oci', '19.0.0')],
        ];
    }

    /**
     * @dataProvider unsupportedDriverProvider
     */
    public function testGetStatementRejectsUnsupportedDriver(DriverInfo $driverInfo): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id'])->update(['name']);

        try {
            $upsert->getStatement($binds, $driverInfo);
            $this->fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Native upsert is not supported', $exception->getMessage());
            $this->assertStringContainsString($driverInfo->getDriver(), $exception->getMessage());
        }

        $this->assertEmpty($binds);
    }

    public function testGetStatementWithSingleRow(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows(['external_id' => 'A', 'name' => 'First'])->uniqueBy(['external_id']);

        $this->assertSame(
            'INSERT INTO users ( `external_id`, `name` ) VALUES ( :_h_0, :_h_1 )' .
            ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)',
            $upsert->getStatement($binds)
        );
        $this->assertSame(['_h_0' => 'A', '_h_1' => 'First'], $this->values($binds));
    }

    public function testGetStatementAlignsValuesOnTheColumnsOfTheFirstRow(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert
            ->from('users')
            ->rows([
                ['external_id' => 'A', 'name' => 'First'],
                ['name' => 'Second', 'external_id' => 'B'],
            ])
            ->uniqueBy(['external_id']);

        $this->assertStringStartsWith(
            'INSERT INTO users ( `external_id`, `name` )' . self::VALUES,
            $upsert->getStatement($binds)
        );
        $this->assertSame(
            ['_h_0' => 'A', '_h_1' => 'First', '_h_2' => 'B', '_h_3' => 'Second'],
            $this->values($binds)
        );
    }

    public static function compositeKeyProvider(): array
    {
        $backtick = 'INSERT INTO prices ( `tenant`, `sku`, `price`, `label` ) VALUES ( :_h_0, :_h_1, :_h_2, :_h_3 )';
        $doubleQuote = 'INSERT INTO prices ( "tenant", "sku", "price", "label" ) VALUES ( :_h_0, :_h_1, :_h_2, :_h_3 )';

        return [
            'mysql 5.7' => [
                new DriverInfo('mysql', '5.7.44'),
                $backtick . ' ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `label` = VALUES(`label`)',
            ],
            'mysql 8.4' => [
                new DriverInfo('mysql', '8.4.0'),
                $backtick . ' AS `hector_upsert` ON DUPLICATE KEY UPDATE ' .
                '`price` = `hector_upsert`.`price`, `label` = `hector_upsert`.`label`',
            ],
            'pgsql' => [
                new DriverInfo('pgsql', '16.0'),
                $doubleQuote . ' ON CONFLICT ( "tenant", "sku" ) DO UPDATE SET ' .
                '"price" = excluded."price", "label" = excluded."label"',
            ],
        ];
    }

    /**
     * Without an update list, every inserted column but the conflict ones is updated.
     *
     * @dataProvider compositeKeyProvider
     */
    public function testGetStatementWithCompositeKeyAndDefaultUpdateList(DriverInfo $driverInfo, string $expected): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert
            ->from('prices')
            ->rows([['tenant' => 1, 'sku' => 'A', 'price' => '9.90', 'label' => null]])
            ->uniqueBy(['tenant', 'sku']);

        $this->assertSame($expected, $upsert->getStatement($binds, $driverInfo));
        $this->assertSame(['_h_0' => 1, '_h_1' => 'A', '_h_2' => '9.90', '_h_3' => null], $this->values($binds));
    }

    public static function emptyUpdateProvider(): array
    {
        $backtick = 'INSERT INTO users ( `external_id`, `name` )' . self::VALUES;
        $doubleQuote = 'INSERT INTO users ( "external_id", "name" )' . self::VALUES;
        $noop = $backtick . ' ON DUPLICATE KEY UPDATE `external_id` = `external_id`';
        $nothing = $doubleQuote . ' ON CONFLICT ( "external_id" ) DO NOTHING';

        return [
            'no driver (fallback)' => [null, $noop],
            'mysql 5.7' => [new DriverInfo('mysql', '5.7.44'), $noop],
            'mysql 8.4' => [new DriverInfo('mysql', '8.4.0'), $noop],
            'mariadb' => [new DriverInfo('mariadb', '11.4.0'), $noop],
            'pgsql' => [new DriverInfo('pgsql', '16.0'), $nothing],
            'sqlite' => [new DriverInfo('sqlite', '3.45.0'), $nothing],
        ];
    }

    /**
     * @dataProvider emptyUpdateProvider
     */
    public function testGetStatementWithEmptyUpdateListLeavesExistingRowsUntouched(
        ?DriverInfo $driverInfo,
        string $expected,
    ): void {
        $upsert = new Upsert();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id'])->update([]);

        $this->assertSame($expected, $upsert->getStatement(new BindParamList(), $driverInfo));
    }

    public function testGetStatementWithOnlyConflictColumnsLeavesExistingRowsUntouched(): void
    {
        $upsert = new Upsert();
        $upsert
            ->from('film_actor')
            ->rows([['film_id' => 1, 'actor_id' => 2], ['film_id' => 1, 'actor_id' => 3]])
            ->uniqueBy(['film_id', 'actor_id']);

        $this->assertSame(
            'INSERT INTO film_actor ( "film_id", "actor_id" )' . self::VALUES .
            ' ON CONFLICT ( "film_id", "actor_id" ) DO NOTHING',
            $upsert->getStatement(new BindParamList(), new DriverInfo('pgsql', '16.0'))
        );
    }

    public function testGetStatementQuotesIdentifiers(): void
    {
        $upsert = new Upsert();
        $upsert
            ->from('users')
            ->rows([['`external_id`' => 'A', '"order"' => 1, 'first name' => 'Alice', 'we`ird"' => 'x']])
            ->uniqueBy(['"external_id"'])
            ->update(['`order`', 'first name', 'we`ird"']);

        $this->assertSame(
            'INSERT INTO users ( `external_id`, `order`, `first name`, `we``ird"` )' .
            ' VALUES ( :_h_0, :_h_1, :_h_2, :_h_3 )' .
            ' ON DUPLICATE KEY UPDATE `order` = VALUES(`order`), `first name` = VALUES(`first name`),' .
            ' `we``ird"` = VALUES(`we``ird"`)',
            $upsert->getStatement(new BindParamList())
        );
        $this->assertSame(
            'INSERT INTO users ( "external_id", "order", "first name", "we`ird""" )' .
            ' VALUES ( :_h_0, :_h_1, :_h_2, :_h_3 )' .
            ' ON CONFLICT ( "external_id" ) DO UPDATE SET "order" = excluded."order",' .
            ' "first name" = excluded."first name", "we`ird""" = excluded."we`ird"""',
            $upsert->getStatement(new BindParamList(), new DriverInfo('sqlite', '3.45.0'))
        );
    }

    public function testGetStatementDoesNotSplitQualifiedColumnNames(): void
    {
        $upsert = new Upsert();
        $upsert->from('users')->rows([['a.b' => 1, 'name' => 'x']])->uniqueBy(['a.b']);

        $this->assertSame(
            'INSERT INTO users ( `a.b`, `name` ) VALUES ( :_h_0, :_h_1 )' .
            ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)',
            $upsert->getStatement(new BindParamList())
        );
    }

    public function testGetStatementRendersStatementValues(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert
            ->from('users')
            ->rows([
                [
                    'external_id' => 'A',
                    'updated_at' => new Raw('CURRENT_TIMESTAMP'),
                    'team_id' => (new Select())->column('id')->from('teams')->where('code', 'core'),
                ],
            ])
            ->uniqueBy(['external_id'])
            ->update(['updated_at']);

        $this->assertSame(
            'INSERT INTO users ( `external_id`, `updated_at`, `team_id` )' .
            ' VALUES ( :_h_0, CURRENT_TIMESTAMP, ( SELECT id FROM teams WHERE code = :_h_1 ) )' .
            ' ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`)',
            $upsert->getStatement($binds)
        );
        $this->assertSame(['_h_0' => 'A', '_h_1' => 'core'], $this->values($binds));
    }

    public function testGetStatementIsRepeatable(): void
    {
        $upsert = new Upsert();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id']);

        $first = new BindParamList();
        $second = new BindParamList();

        $this->assertSame($upsert->getStatement($first), $upsert->getStatement($second));
        $this->assertSame($this->values($first), $this->values($second));
    }

    public function testRowsReplacesPreviousRows(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows(self::ROWS)->rows([['code' => 'X']])->uniqueBy(['code']);

        $this->assertSame(
            'INSERT INTO users ( `code` ) VALUES ( :_h_0 ) ON DUPLICATE KEY UPDATE `code` = `code`',
            $upsert->getStatement($binds)
        );
        $this->assertSame(['_h_0' => 'X'], $this->values($binds));
    }

    public function testReset(): void
    {
        $upsert = new Upsert();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id'])->update(['name']);
        $upsert->reset();

        $this->assertNull($upsert->getStatement(new BindParamList()));
    }

    public static function invalidRowsProvider(): array
    {
        return [
            'empty row' => [[[]], 'row "0" must be a non-empty array'],
            'row is not an array' => [[['code' => 'A'], 'B'], 'row "1" must be a non-empty array'],
            'row without column names' => [[['A', 'First']], 'row "0" must be indexed by column name'],
            'single row without column names' => [['A', 'First'], 'row "0" must be indexed by column name'],
            'empty column name' => [[['' => 'A']], 'row "0" must be indexed by column name'],
            'missing column' => [
                [['code' => 'A', 'name' => 'First'], ['code' => 'B']],
                'row "1" does not have the same columns as the first row',
            ],
            'additional column' => [
                [['code' => 'A'], ['code' => 'B', 'name' => 'Second']],
                'row "1" does not have the same columns as the first row',
            ],
            'different column' => [
                ['first' => ['code' => 'A', 'name' => 'First'], 'second' => ['code' => 'B', 'label' => 'Second']],
                'row "second" does not have the same columns as the first row',
            ],
            'column defined twice' => [[['code' => 'A', '`code`' => 'B']], 'row "0" defines column "code" twice'],
            'array value' => [[['code' => ['A', 'B']]], 'unsupported value of type array for column "code"'],
            'closure value' => [[['code' => fn(): string => 'A']], 'unsupported value of type Closure'],
            'assignment value' => [[['code' => 'A', 'hits' => new Increment()]], 'for column "hits"'],
        ];
    }

    /**
     * @dataProvider invalidRowsProvider
     */
    public function testRowsRejectsInvalidInput(array $rows, string $message): void
    {
        $upsert = new Upsert();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $upsert->rows($rows);
    }

    public static function invalidUniqueByProvider(): array
    {
        return [
            'empty list' => [[], 'requires at least one conflict column'],
            'not a column name' => [[1], 'conflict columns must be a list of column names'],
            'empty column name' => [[''], 'conflict columns must be a list of column names'],
            'indexed by name' => [['code' => 'A'], 'conflict columns must be a list of column names'],
        ];
    }

    /**
     * @dataProvider invalidUniqueByProvider
     */
    public function testUniqueByRejectsInvalidInput(array $uniqueBy, string $message): void
    {
        $upsert = new Upsert();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $upsert->uniqueBy($uniqueBy);
    }

    public static function invalidUpdateProvider(): array
    {
        return [
            'not a column name' => [[null], 'update columns must be a list of column names'],
            'empty column name' => [['  '], 'update columns must be a list of column names'],
            'column with a value' => [['name' => 'forced'], 'update columns must be a list of column names'],
            'column with an assignment value' => [
                ['hits' => new Increment()],
                'update columns must be a list of column names',
            ],
        ];
    }

    /**
     * @dataProvider invalidUpdateProvider
     */
    public function testUpdateRejectsInvalidInput(array $update, string $message): void
    {
        $upsert = new Upsert();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $upsert->update($update);
    }

    public function testGetStatementRequiresConflictColumns(): void
    {
        $upsert = new Upsert();
        $binds = new BindParamList();
        $upsert->from('users')->rows(self::ROWS);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires at least one conflict column');

        $upsert->getStatement($binds);
    }

    public function testGetStatementRejectsConflictColumnMissingFromRows(): void
    {
        $upsert = new Upsert();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id', 'tenant']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflict column "tenant" is not part of the rows');

        $upsert->getStatement(new BindParamList());
    }

    public function testGetStatementRejectsUpdateColumnMissingFromRows(): void
    {
        $upsert = new Upsert();
        $upsert->from('users')->rows(self::ROWS)->uniqueBy(['external_id'])->update(['name', 'email']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('update column "email" is not part of the rows');

        $upsert->getStatement(new BindParamList());
    }
}
