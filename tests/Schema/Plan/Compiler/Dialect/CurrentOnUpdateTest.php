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

namespace Hector\Schema\Tests\Plan\Compiler\Dialect;

use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverInfo;
use Hector\Connection\Driver\MySQLCapabilities;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class CurrentOnUpdateTest extends TestCase
{
    /** @dataProvider temporalTypes */
    public function testCreateAddAndModify(string $type, string $expression): void
    {
        $plan = new Plan();
        $create = $plan->create('events');
        $create->addColumn('updated_at', $type, default: new Raw($expression), useCurrentOnUpdate: true);
        $this->assertTrue($create->getArrayCopy()[0]->usesCurrentOnUpdate());
        $plan->alter('events')->addColumn('added_at', $type, nullable: true, useCurrentOnUpdate: true);
        $plan->alter('events')->modifyColumn('updated_at', $type, useCurrentOnUpdate: true);
        $plan->alter('events')->modifyColumn('updated_at', $type);

        $this->assertSame([
            "CREATE TABLE `events` (\n  `updated_at` $type NOT NULL DEFAULT $expression ON UPDATE $expression\n)",
            "ALTER TABLE `events` ADD COLUMN `added_at` $type NULL DEFAULT NULL ON UPDATE $expression",
            "ALTER TABLE `events` MODIFY COLUMN `updated_at` $type NOT NULL ON UPDATE $expression",
            "ALTER TABLE `events` MODIFY COLUMN `updated_at` $type NOT NULL",
        ], iterator_to_array($plan->getStatements(new MySQLCompiler()), false));
    }

    public static function temporalTypes(): array
    {
        return [
            ['TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['datetime', 'CURRENT_TIMESTAMP'],
            ['TIMESTAMP(0)', 'CURRENT_TIMESTAMP(0)'],
            ['datetime(3)', 'CURRENT_TIMESTAMP(3)'],
            ['DATETIME(6)', 'CURRENT_TIMESTAMP(6)'],
        ];
    }

    /** @dataProvider invalidTypes */
    public function testInvalidMysqlDefinition(string $type, bool $autoIncrement): void
    {
        $plan = new Plan();
        $plan->create('events')->addColumn('updated_at', $type,
            autoIncrement: $autoIncrement, useCurrentOnUpdate: true);
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('useCurrentOnUpdate requires TIMESTAMP or DATETIME');
        iterator_to_array($plan->getStatements(new MySQLCompiler()), false);
    }

    public static function invalidTypes(): array
    {
        return [['INT', false], ['DATE', false], ['TIMESTAMP(7)', false], ['DATETIME', true]];
    }

    public function testSqliteOmitsOptionOnCreateAndAdd(): void
    {
        $plan = new Plan();
        $plan->create('events')->addColumn('updated_at', 'TIMESTAMP',
            default: new Raw('CURRENT_TIMESTAMP'), useCurrentOnUpdate: true);
        $plan->alter('events')->addColumn('modified_at', 'DATETIME', nullable: true, useCurrentOnUpdate: true);
        $this->assertSame([
            "CREATE TABLE \"events\" (\n  \"updated_at\" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n)",
            'ALTER TABLE "events" ADD COLUMN "modified_at" DATETIME DEFAULT NULL',
        ], iterator_to_array($plan->getStatements(new SqliteCompiler()), false));
    }

    public function testLegacyRenamePreservesExpressionAndPrecision(): void
    {
        $column = new Column('updated_at', 0, 'current_timestamp(6)', false, 'datetime',
            on_update: 'CURRENT_TIMESTAMP(6)', datetime_precision: 6);
        $table = new Table(schema_name: 'mydb', name: 'events', type: Table::TYPE_TABLE,
            columns: ['updated_at' => $column]);
        $schema = new Schema(connection: 'default', name: 'mydb', charset: 'utf8mb4', tables: ['events' => $table]);
        $plan = new Plan();
        $plan->alter('events')->renameColumn('updated_at', 'modified_at');
        $compiler = new MySQLCompiler(new MySQLCapabilities(new DriverInfo('mysql', '5.7.44')));
        $this->assertSame([
            'ALTER TABLE `events` CHANGE COLUMN `updated_at` `modified_at` datetime(6) NOT NULL '
            . 'DEFAULT current_timestamp(6) ON UPDATE CURRENT_TIMESTAMP(6)',
        ], iterator_to_array($plan->getStatements($compiler, $schema), false));
    }

    public function testColumnSerializationAndLegacyCache(): void
    {
        $column = new Column('updated_at', 0, null, true, 'datetime',
            on_update: 'CURRENT_TIMESTAMP(6)', datetime_precision: 6);
        $restored = unserialize(serialize($column));
        $this->assertSame('CURRENT_TIMESTAMP(6)', $restored->getOnUpdate());
        $this->assertSame(6, $restored->getDatetimePrecision());
        $data = $column->__serialize();
        unset($data['on_update'], $data['datetime_precision']);
        $restored->__unserialize($data);
        $this->assertNull($restored->getOnUpdate());
        $this->assertNull($restored->getDatetimePrecision());
    }

    /** @dataProvider extraValues */
    public function testIntrospectionNormalization(string $extra, ?string $expected): void
    {
        $generator = new class(new Connection('sqlite::memory:')) extends MySQL {
            public function getOnUpdateValue(string $extra): ?string
            {
                return parent::getOnUpdateValue($extra);
            }
        };
        $this->assertSame($expected, $generator->getOnUpdateValue($extra));
    }

    public static function extraValues(): array
    {
        return [
            ['', null],
            ['auto_increment', null],
            ['DEFAULT_GENERATED', null],
            ['on update CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            ['on update current_timestamp()', 'CURRENT_TIMESTAMP'],
            ['on update current_timestamp(0)', 'CURRENT_TIMESTAMP'],
            ['DEFAULT_GENERATED on update CURRENT_TIMESTAMP(3)', 'CURRENT_TIMESTAMP(3)'],
            ['on update current_timestamp(6)', 'CURRENT_TIMESTAMP(6)'],
        ];
    }
}
