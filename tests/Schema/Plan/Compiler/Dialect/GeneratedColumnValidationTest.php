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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\MySQLDialect;
use Hector\Schema\Plan\Compiler\Dialect\SqliteDialect;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

class GeneratedColumnValidationTest extends TestCase
{
    /**
     * @dataProvider incompatibleDefinitions
     */
    public function testRejectsIncompatibleAttributes(
        string $driver,
        string $entryPoint,
        array $options,
        string $attribute,
    ): void {
        $dialect = 'mysql' === $driver ? new MySQLDialect() : new SqliteDialect();
        $options['generated'] = 'quantity * price';
        $create = new CreateTable('items');
        $alter = new AlterTable('items');
        // The rebuild context deliberately has no table: validation must happen
        // before introspection/transformation and report the incompatible attribute.
        $context = new CompilationContext('rebuild' === $entryPoint
            ? new Schema(connection: 'default', name: 'main', charset: 'utf8mb4')
            : null);

        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('Generated column "total" on table "items" cannot specify ' . $attribute);

        $statements = match ($entryPoint) {
            'create' => $dialect->compileCreateTable($create->addColumn('total', 'INTEGER', ...$options)),
            'add' => $dialect->compileAlterTable($alter->addColumn('total', 'INTEGER', ...$options), $context),
            'modify', 'rebuild' => $dialect->compileAlterTable(
                $alter->modifyColumn('total', 'INTEGER', ...$options),
                $context,
            ),
            'standalone add' => $dialect->compileStandaloneOperation(
                new AddColumn('items', 'total', 'INTEGER', ...$options),
            ),
            'standalone modify' => $dialect->compileStandaloneOperation(
                new ModifyColumn('items', 'total', 'INTEGER', ...$options),
            ),
        };

        // Force validation in generator-based compilation paths as well.
        foreach ($statements as $statement) {
            $this->fail('Invalid column definition emitted SQL: ' . $statement);
        }
    }

    public static function incompatibleDefinitions(): iterable
    {
        $definitions = [
            'literal default' => [['default' => 10], 'DEFAULT'],
            'zero default' => [['default' => 0], 'DEFAULT'],
            'false default' => [['default' => false], 'DEFAULT'],
            'expression default' => [['default' => new Raw('1 + 1')], 'DEFAULT'],
            'explicit NULL default' => [['nullable' => true, 'hasDefault' => true], 'DEFAULT'],
            'disabled supplied default' => [['default' => 10, 'hasDefault' => false], 'DEFAULT'],
            'auto increment' => [['autoIncrement' => true], 'AUTO_INCREMENT'],
            'automatic timestamp' => [['useCurrentOnUpdate' => true], 'useCurrentOnUpdate'],
        ];

        foreach (['mysql', 'sqlite'] as $driver) {
            foreach (['create', 'add', 'modify', 'standalone add', 'standalone modify', 'rebuild'] as $entryPoint) {
                foreach ($definitions as $name => [$options, $attribute]) {
                    yield "$driver / $entryPoint / $name" => [$driver, $entryPoint, $options, $attribute];
                }
            }
        }
    }

    /**
     * @dataProvider validNullability
     */
    public function testAllowsGeneratedColumnsWithoutDefaults(string $driver, bool $nullable): void
    {
        $dialect = 'mysql' === $driver ? new MySQLDialect() : new SqliteDialect();
        $create = new CreateTable('items');
        $create->addColumn('total', 'INTEGER', nullable: $nullable, generated: 'quantity * price');
        $alter = new AlterTable('items');
        $alter->addColumn('total', 'INTEGER', nullable: $nullable, generated: 'quantity * price');

        $statements = [
            ...$dialect->compileCreateTable($create),
            ...$dialect->compileAlterTable($alter, new CompilationContext()),
        ];

        $this->assertCount(2, $statements);
        foreach ($statements as $statement) {
            $this->assertStringNotContainsString('DEFAULT', $statement);
        }
    }

    public static function validNullability(): array
    {
        return [['mysql', true], ['mysql', false], ['sqlite', true], ['sqlite', false]];
    }
}
