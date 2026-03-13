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

use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\DropColumn;
use Hector\Schema\Plan\OperationGroupInterface;
use Hector\Schema\Plan\OperationInterface;
use InvalidArgumentException;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Class OperationGroupTest.
 *
 * Tests the OperationGroup behaviour via its concrete subclass AlterTable,
 * since OperationGroup::add() is protected and only exposed
 * through business methods on TableOperation (addColumn, dropColumn, etc.).
 */
class OperationGroupTest extends TestCase
{
    public function testConstructor(): void
    {
        $group = new AlterTable('users');

        $this->assertSame('users', $group->getObjectName());
        $this->assertTrue($group->isEmpty());
        $this->assertCount(0, $group);
        $this->assertSame([], $group->getArrayCopy());
    }

    public function testImplementsOperationGroupInterface(): void
    {
        $group = new AlterTable('users');

        $this->assertInstanceOf(OperationGroupInterface::class, $group);
        $this->assertInstanceOf(OperationInterface::class, $group);
    }

    public function testCountIncrementsWithOperations(): void
    {
        $group = new AlterTable('users');
        $this->assertCount(0, $group);

        $group->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
        $this->assertCount(1, $group);

        $group->dropColumn('legacy');
        $this->assertCount(2, $group);
    }

    public function testIsEmpty(): void
    {
        $group = new AlterTable('users');
        $this->assertTrue($group->isEmpty());

        $group->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
        $this->assertFalse($group->isEmpty());
    }

    public function testIteratorAggregate(): void
    {
        $group = new AlterTable('users');
        $this->assertInstanceOf(IteratorAggregate::class, $group);

        $group->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
        $group->dropColumn('legacy');

        $operations = [];
        foreach ($group as $operation) {
            $operations[] = $operation;
        }

        $this->assertCount(2, $operations);
        $this->assertInstanceOf(AddColumn::class, $operations[0]);
        $this->assertInstanceOf(DropColumn::class, $operations[1]);
    }

    public function testGetArrayCopy(): void
    {
        $group = new AlterTable('users');

        $group->addColumn('email', 'varchar(255)', hasDefault: true, default: '');
        $group->dropColumn('legacy');

        $copy = $group->getArrayCopy();

        $this->assertCount(2, $copy);
        $this->assertInstanceOf(AddColumn::class, $copy[0]);
        $this->assertInstanceOf(DropColumn::class, $copy[1]);
    }

    public function testAddValidatesObjectName(): void
    {
        // AddColumn with a different table name should trigger the validation.
        // We use a CreateTable to add a column that belongs to a different table
        // via reflection to bypass the protected visibility.
        $group = new AlterTable('users');

        $wrongColumn = new AddColumn(
            table: 'orders',
            name: 'total',
            type: 'decimal(10,2)',
        );

        $this->expectException(InvalidArgumentException::class);

        // Use reflection to call the protected add() method
        $reflection = new ReflectionMethod($group, 'add');
        $reflection->setAccessible(true);
        $reflection->invoke($group, $wrongColumn);
    }

    public function testAddAcceptsMatchingObjectName(): void
    {
        $group = new AlterTable('users');

        $column = new AddColumn(
            table: 'users',
            name: 'email',
            type: 'varchar(255)',
        );

        // Use reflection to call the protected add() method
        $reflection = new ReflectionMethod($group, 'add');
        $reflection->setAccessible(true);
        $reflection->invoke($group, $column);

        $this->assertCount(1, $group);
    }
}
