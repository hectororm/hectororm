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

namespace Hector\Schema\Tests\Plan\Compiler\Dialect\Sqlite;

use Hector\Schema\Index;
use Hector\Schema\Column;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\ColumnDef;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\ForeignKeyDef;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\IndexDef;
use Hector\Schema\Plan\Compiler\Dialect\Sqlite\TableDiff;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use PHPUnit\Framework\TestCase;

/**
 * Class TableDiffTest.
 *
 * Tests the SQLite rebuild diff model that replaced the previous
 * assoc-array + IIFE approach.
 */
class TableDiffTest extends TestCase
{
    public function testGeneratedDefinitionsSurviveSchemaAndOperationConversion(): void
    {
        $column = new Column('total', 0, null, true, 'int',
            generation_expression: 'quantity * price', generated_stored: true);
        $definition = ColumnDef::fromSchema($column, 'INTEGER');
        $this->assertSame('quantity * price', $definition->generated->getExpression());
        $this->assertTrue($definition->generated->isStored());
        $this->assertFalse($definition->hasDefault);
        $this->assertSame($definition->generated, $definition->withName('renamed')->generated);

        $operation = new ModifyColumn('items', 'total', 'INTEGER', generated: 'quantity * price');
        $this->assertSame($operation->getGenerated(), ColumnDef::fromOperation($operation)->generated);
    }

    public function testMigrationMappingFollowsTargetWritabilityAndRenameChains(): void
    {
        $diff = $this->diffWithColumns('quantity', 'price', 'total', 'materialized');
        $diff->apply(new ModifyColumn('t', 'total', 'INTEGER', generated: 'quantity * price'));
        $diff->apply(new RenameColumn('t', 'QUANTITY', 'amount'));
        $diff->apply(new RenameColumn('t', 'amount', 'units'));
        $diff->apply(new DropColumn('t', 'materialized'));
        $diff->apply(new AddColumn('t', 'materialized', 'INTEGER', default: 0, hasDefault: true));

        $this->assertSame(['quantity' => 'units', 'price' => 'price'], $diff->migrateMapping());
        $this->assertSame(['units', 'price', 'total', 'materialized'], array_keys($diff->columns()));
        $this->assertSame('"units" * price', $diff->columns()['total']->generated->getExpression());
    }

    public function testGeneratedSourceCanBecomeWritable(): void
    {
        $definition = new ColumnDef('total', 'INTEGER', false, null, false, false, new Generated('quantity * price'));
        $diff = new TableDiff(['quantity' => $this->column('quantity'), 'total' => $definition], [], []);
        $diff->apply(new ModifyColumn('t', 'total', 'INTEGER'));
        $diff->apply(new RenameColumn('t', 'total', 'snapshot'));

        $this->assertSame(['quantity' => 'quantity', 'total' => 'snapshot'], $diff->migrateMapping());
    }

    public function testRenameUpdatesIndexesAndLocalAndSelfReferencingForeignKeys(): void
    {
        $diff = new TableDiff(
            ['id' => $this->column('id')],
            ['idx_id' => new IndexDef('idx_id', ['id'], Index::INDEX)],
            [
                'self' => new ForeignKeyDef('self', ['id'], 'items', ['id'], 'NO ACTION', 'NO ACTION'),
                'external' => new ForeignKeyDef('external', ['id'], 'other', ['id'], 'NO ACTION', 'NO ACTION'),
            ],
            'items',
        );
        $diff->apply(new RenameColumn('items', 'id', 'entry_id'));

        $this->assertSame(['entry_id'], $diff->nonPrimaryIndexes()[0]->columns);
        $this->assertSame(['entry_id'], $diff->foreignKeys()['self']->columns);
        $this->assertSame(['entry_id'], $diff->foreignKeys()['self']->referencedColumns);
        $this->assertSame(['entry_id'], $diff->foreignKeys()['external']->columns);
        $this->assertSame(['id'], $diff->foreignKeys()['external']->referencedColumns);
    }

    public function testRenameCannotOverwriteAnExistingColumn(): void
    {
        $diff = $this->diffWithColumns('quantity', 'price');
        $this->expectException(PlanException::class);
        $diff->apply(new RenameColumn('items', 'quantity', 'PRICE'));
    }

    private function column(string $name, string $type = 'TEXT'): ColumnDef
    {
        return new ColumnDef($name, $type, false, null, false, false);
    }

    private function diffWithColumns(string ...$names): TableDiff
    {
        $columns = [];
        foreach ($names as $name) {
            $columns[$name] = $this->column($name);
        }

        return new TableDiff($columns, [], []);
    }

    public function testApplyAddColumn(): void
    {
        $diff = $this->diffWithColumns('id');
        $diff->apply(new AddColumn('t', 'name', 'varchar(50)', nullable: true));

        $columns = $diff->columns();
        $this->assertSame(['id', 'name'], array_keys($columns));
        $this->assertSame('varchar(50)', $columns['name']->type);
        $this->assertTrue($columns['name']->nullable);
    }

    public function testApplyDropColumn(): void
    {
        $diff = $this->diffWithColumns('id', 'legacy');
        $diff->apply(new DropColumn('t', 'legacy'));

        $this->assertSame(['id'], array_keys($diff->columns()));
    }

    public function testApplyModifyColumnReplacesDefinition(): void
    {
        $diff = $this->diffWithColumns('id', 'name');
        $diff->apply(new ModifyColumn('t', 'name', 'TEXT', nullable: true));

        $this->assertSame('TEXT', $diff->columns()['name']->type);
        $this->assertTrue($diff->columns()['name']->nullable);
    }

    public function testApplyModifyColumnOnUnknownColumnIsNoop(): void
    {
        $diff = $this->diffWithColumns('id');
        $diff->apply(new ModifyColumn('t', 'ghost', 'TEXT'));

        $this->assertSame(['id'], array_keys($diff->columns()));
    }

    public function testApplyRenameColumnUpdatesKeyAndName(): void
    {
        $diff = $this->diffWithColumns('id', 'fullname');
        $diff->apply(new RenameColumn('t', 'fullname', 'display_name'));

        $columns = $diff->columns();
        $this->assertSame(['id', 'display_name'], array_keys($columns));
        $this->assertSame('display_name', $columns['display_name']->name);
    }

    public function testApplyIndexOperations(): void
    {
        $diff = new TableDiff(
            ['id' => $this->column('id')],
            [
                'PRIMARY' => new IndexDef('PRIMARY', ['id'], Index::PRIMARY),
                'idx_old' => new IndexDef('idx_old', ['x'], Index::INDEX),
            ],
            [],
        );

        $diff->apply(new AddIndex('t', 'idx_new', ['y'], Index::UNIQUE));
        $diff->apply(new DropIndex('t', 'idx_old'));

        $primaryNames = array_map(fn(IndexDef $i): string => $i->name, $diff->primaryIndexes());
        $nonPrimaryNames = array_map(fn(IndexDef $i): string => $i->name, $diff->nonPrimaryIndexes());

        $this->assertSame(['PRIMARY'], $primaryNames);
        $this->assertSame(['idx_new'], $nonPrimaryNames);
    }

    public function testApplyForeignKeyOperations(): void
    {
        $diff = new TableDiff(
            ['id' => $this->column('id')],
            [],
            ['fk_old' => new ForeignKeyDef('fk_old', ['a'], 'other', ['id'], 'NO ACTION', 'NO ACTION')],
        );

        $diff->apply(new AddForeignKey('t', 'fk_new', ['b'], 'ref', ['id']));
        $diff->apply(new DropForeignKey('t', 'fk_old'));

        $this->assertSame(['fk_new'], array_keys($diff->foreignKeys()));
    }

    public function testMigrateMappingKeepsSurvivingColumns(): void
    {
        $diff = $this->diffWithColumns('id', 'name', 'legacy');
        $diff->apply(new DropColumn('t', 'legacy'));
        $diff->apply(new AddColumn('t', 'created_at', 'datetime', nullable: true));

        // Only columns present before AND after are migrated; the new column is not.
        $this->assertSame(
            ['id' => 'id', 'name' => 'name'],
            $diff->migrateMapping(),
        );
    }

    public function testMigrateMappingFollowsRenames(): void
    {
        $diff = $this->diffWithColumns('id', 'fullname');
        $diff->apply(new RenameColumn('t', 'fullname', 'display_name'));

        $this->assertSame(
            ['id' => 'id', 'fullname' => 'display_name'],
            $diff->migrateMapping(),
        );
    }
}
