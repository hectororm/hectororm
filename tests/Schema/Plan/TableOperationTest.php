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

namespace Hector\Schema\Tests\Plan;

use Hector\Schema\Column;
use Hector\Schema\ForeignKey;
use Hector\Schema\Index;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\AddIndex;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\DropIndex;
use Hector\Schema\Plan\Operation\ModifyColumn;
use Hector\Schema\Plan\Operation\RenameColumn;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

/**
 * Class TableOperationTest.
 */
class TableOperationTest extends TestCase
{
    // =========================================================================
    // addColumn
    // =========================================================================

    public function testAddColumn(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->addColumn('email', 'varchar(255)');

        $this->assertSame($tableOp, $result);
        $this->assertCount(1, $tableOp);
        $this->assertFalse($tableOp->isEmpty());

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(AddColumn::class, $op);
        $this->assertSame('users', $op->getObjectName());
        $this->assertSame('email', $op->getName());
        $this->assertSame('varchar(255)', $op->getType());
        $this->assertFalse($op->isNullable());
        $this->assertFalse($op->hasDefault());
        $this->assertFalse($op->isAutoIncrement());
        $this->assertNull($op->getAfter());
        $this->assertFalse($op->isFirst());
    }

    public function testAddColumnWithAllOptions(): void
    {
        $tableOp = new AlterTable('users');
        $tableOp->addColumn(
            'status',
            'varchar(20)',
            nullable: true,
            default: 'active',
            hasDefault: true,
            autoIncrement: false,
            after: 'name',
            first: false,
        );

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(AddColumn::class, $op);
        $this->assertTrue($op->isNullable());
        $this->assertTrue($op->hasDefault());
        $this->assertSame('active', $op->getDefault());
        $this->assertSame('name', $op->getAfter());
    }

    public function testAddColumnFirst(): void
    {
        $tableOp = new AlterTable('users');
        $tableOp->addColumn('id', 'int', autoIncrement: true, first: true);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(AddColumn::class, $op);
        $this->assertTrue($op->isFirst());
        $this->assertTrue($op->isAutoIncrement());
    }

    // =========================================================================
    // dropColumn
    // =========================================================================

    public function testDropColumn(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->dropColumn('old_field');

        $this->assertSame($tableOp, $result);
        $this->assertCount(1, $tableOp);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropColumn::class, $op);
        $this->assertSame('old_field', $op->getName());
    }

    public function testDropColumnAcceptsColumnObject(): void
    {
        $column = new Column('old_field', 1, null, false, 'varchar');
        $tableOp = new AlterTable('users');
        $tableOp->dropColumn($column);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropColumn::class, $op);
        $this->assertSame('old_field', $op->getName());
    }

    // =========================================================================
    // modifyColumn
    // =========================================================================

    public function testModifyColumn(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->modifyColumn('name', 'varchar(500)');

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(ModifyColumn::class, $op);
        $this->assertSame('name', $op->getName());
        $this->assertSame('varchar(500)', $op->getType());
    }

    public function testModifyColumnAcceptsColumnObject(): void
    {
        $column = new Column('name', 1, null, false, 'varchar');
        $tableOp = new AlterTable('users');
        $tableOp->modifyColumn($column, 'varchar(500)', nullable: true);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(ModifyColumn::class, $op);
        $this->assertSame('name', $op->getName());
        $this->assertTrue($op->isNullable());
    }

    // =========================================================================
    // renameColumn
    // =========================================================================

    public function testRenameColumn(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->renameColumn('fullname', 'display_name');

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(RenameColumn::class, $op);
        $this->assertSame('fullname', $op->getName());
        $this->assertSame('display_name', $op->getNewName());
    }

    public function testRenameColumnAcceptsColumnObject(): void
    {
        $column = new Column('fullname', 1, null, false, 'varchar');
        $tableOp = new AlterTable('users');
        $tableOp->renameColumn($column, 'display_name');

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(RenameColumn::class, $op);
        $this->assertSame('fullname', $op->getName());
    }

    // =========================================================================
    // addIndex
    // =========================================================================

    public function testAddIndex(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->addIndex('idx_name', ['name']);

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(AddIndex::class, $op);
        $this->assertSame('idx_name', $op->getName());
        $this->assertSame(['name'], $op->getColumns());
        $this->assertSame(Index::INDEX, $op->getType());
    }

    public function testAddUniqueIndex(): void
    {
        $tableOp = new AlterTable('users');
        $tableOp->addIndex('idx_email', ['email'], Index::UNIQUE);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertSame(Index::UNIQUE, $op->getType());
    }

    public function testAddPrimaryIndex(): void
    {
        $tableOp = new AlterTable('users');
        $tableOp->addIndex('PRIMARY', ['id'], Index::PRIMARY);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertSame(Index::PRIMARY, $op->getType());
    }

    public function testAddCompositeIndex(): void
    {
        $tableOp = new AlterTable('users');
        $tableOp->addIndex('idx_name_email', ['name', 'email']);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertSame(['name', 'email'], $op->getColumns());
    }

    // =========================================================================
    // dropIndex
    // =========================================================================

    public function testDropIndex(): void
    {
        $tableOp = new AlterTable('users');
        $result = $tableOp->dropIndex('idx_old');

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropIndex::class, $op);
        $this->assertSame('idx_old', $op->getName());
    }

    public function testDropIndexAcceptsIndexObject(): void
    {
        $index = new Index('idx_old', Index::INDEX, ['name']);
        $tableOp = new AlterTable('users');
        $tableOp->dropIndex($index);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropIndex::class, $op);
        $this->assertSame('idx_old', $op->getName());
    }

    // =========================================================================
    // addForeignKey
    // =========================================================================

    public function testAddForeignKey(): void
    {
        $tableOp = new AlterTable('posts');
        $result = $tableOp->addForeignKey('fk_user', ['user_id'], 'users', ['id']);

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(AddForeignKey::class, $op);
        $this->assertSame('fk_user', $op->getName());
        $this->assertSame(['user_id'], $op->getColumns());
        $this->assertSame('users', $op->getReferencedTable());
        $this->assertSame(['id'], $op->getReferencedColumns());
        $this->assertSame(ForeignKey::RULE_NO_ACTION, $op->getOnUpdate());
        $this->assertSame(ForeignKey::RULE_NO_ACTION, $op->getOnDelete());
    }

    public function testAddForeignKeyWithRules(): void
    {
        $tableOp = new AlterTable('posts');
        $tableOp->addForeignKey(
            'fk_user',
            ['user_id'],
            'users',
            ['id'],
            onUpdate: ForeignKey::RULE_CASCADE,
            onDelete: ForeignKey::RULE_SET_NULL,
        );

        $op = $tableOp->getArrayCopy()[0];
        $this->assertSame(ForeignKey::RULE_CASCADE, $op->getOnUpdate());
        $this->assertSame(ForeignKey::RULE_SET_NULL, $op->getOnDelete());
    }

    public function testAddForeignKeyAcceptsTableObject(): void
    {
        $refTable = new Table('mydb', Table::TYPE_TABLE, 'users');
        $tableOp = new AlterTable('posts');
        $tableOp->addForeignKey('fk_user', ['user_id'], $refTable, ['id']);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertSame('users', $op->getReferencedTable());
    }

    // =========================================================================
    // dropForeignKey
    // =========================================================================

    public function testDropForeignKey(): void
    {
        $tableOp = new AlterTable('posts');
        $result = $tableOp->dropForeignKey('fk_user');

        $this->assertSame($tableOp, $result);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropForeignKey::class, $op);
        $this->assertSame('fk_user', $op->getName());
    }

    public function testDropForeignKeyAcceptsForeignKeyObject(): void
    {
        $fk = new ForeignKey('fk_user', ['user_id'], 'mydb', 'users', ['id']);
        $tableOp = new AlterTable('posts');
        $tableOp->dropForeignKey($fk);

        $op = $tableOp->getArrayCopy()[0];
        $this->assertInstanceOf(DropForeignKey::class, $op);
        $this->assertSame('fk_user', $op->getName());
    }

    // =========================================================================
    // Fluent chaining
    // =========================================================================

    public function testFluentChaining(): void
    {
        $tableOp = new AlterTable('users');

        $result = $tableOp
            ->addColumn('email', 'varchar(255)')
            ->addColumn('phone', 'varchar(20)', nullable: true)
            ->dropColumn('legacy')
            ->renameColumn('fullname', 'display_name')
            ->modifyColumn('name', 'varchar(500)')
            ->addIndex('idx_email', ['email'], Index::UNIQUE)
            ->dropIndex('idx_old')
            ->addForeignKey('fk_role', ['role_id'], 'roles', ['id'])
            ->dropForeignKey('fk_old');

        $this->assertSame($tableOp, $result);
        $this->assertCount(9, $tableOp);
    }
}
