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

use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Plan\Operation\CreateView;
use Hector\Schema\Plan\Operation\DropTable;
use Hector\Schema\Plan\Operation\TableOperationInterface;
use Hector\Schema\Plan\Operation\ViewOperationInterface;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;

class ObjectPlanTest extends TestCase
{
    public function testConstructor(): void
    {
        $objectPlan = new ObjectPlan('users');

        $this->assertSame('users', $objectPlan->getName());
        $this->assertTrue($objectPlan->isEmpty());
        $this->assertCount(0, $objectPlan);
        $this->assertSame([], $objectPlan->getArrayCopy());
    }

    public function testCountIncrementsWithOperations(): void
    {
        $objectPlan = new ObjectPlan('users');
        $this->assertCount(0, $objectPlan);

        $objectPlan->addOperation(new DropTable('users'));
        $this->assertCount(1, $objectPlan);

        $objectPlan->addOperation(new CreateView('active_users', 'SELECT id FROM users'));
        $this->assertCount(2, $objectPlan);
    }

    public function testIsEmpty(): void
    {
        $objectPlan = new ObjectPlan('users');
        $this->assertTrue($objectPlan->isEmpty());

        $objectPlan->addOperation(new DropTable('users'));
        $this->assertFalse($objectPlan->isEmpty());
    }

    public function testIteratorAggregate(): void
    {
        $objectPlan = new ObjectPlan('users');
        $this->assertInstanceOf(IteratorAggregate::class, $objectPlan);

        $objectPlan->addOperation(new DropTable('users'));
        $objectPlan->addOperation(new CreateView('active_users', 'SELECT id FROM users'));

        $operations = [];
        foreach ($objectPlan as $operation) {
            $operations[] = $operation;
        }

        $this->assertCount(2, $operations);
        $this->assertInstanceOf(DropTable::class, $operations[0]);
        $this->assertInstanceOf(CreateView::class, $operations[1]);
    }

    public function testHas(): void
    {
        $objectPlan = new ObjectPlan('users');
        $this->assertFalse($objectPlan->has(TableOperationInterface::class));
        $this->assertFalse($objectPlan->has(DropTable::class));

        $objectPlan->addOperation(new DropTable('users'));
        $this->assertTrue($objectPlan->has(TableOperationInterface::class));
        $this->assertTrue($objectPlan->has(DropTable::class));
        $this->assertFalse($objectPlan->has(ViewOperationInterface::class));
    }

    public function testFilter(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));
        $objectPlan->addOperation(new CreateView('active_users', 'SELECT id FROM users'));

        $tables = $objectPlan->filter(TableOperationInterface::class);

        $this->assertInstanceOf(ObjectPlan::class, $tables);
        $this->assertSame('users', $tables->getName());
        $this->assertCount(1, $tables);
        $this->assertInstanceOf(DropTable::class, $tables->getArrayCopy()[0]);
    }

    public function testFilterReturnsNewInstance(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));

        $filtered = $objectPlan->filter(DropTable::class);

        $this->assertNotSame($objectPlan, $filtered);
    }

    public function testFilterEmpty(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));

        $views = $objectPlan->filter(ViewOperationInterface::class);

        $this->assertTrue($views->isEmpty());
        $this->assertCount(0, $views);
    }

    public function testWithout(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));
        $objectPlan->addOperation(new CreateView('active_users', 'SELECT id FROM users'));

        $withoutViews = $objectPlan->without(ViewOperationInterface::class);

        $this->assertCount(1, $withoutViews);
        $this->assertInstanceOf(DropTable::class, $withoutViews->getArrayCopy()[0]);
    }

    public function testWithoutReturnsNewInstance(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));

        $without = $objectPlan->without(ViewOperationInterface::class);

        $this->assertNotSame($objectPlan, $without);
        $this->assertCount(1, $without);
    }

    public function testWithoutDoesNotModifyOriginal(): void
    {
        $objectPlan = new ObjectPlan('users');
        $objectPlan->addOperation(new DropTable('users'));
        $objectPlan->addOperation(new CreateView('active_users', 'SELECT id FROM users'));

        $objectPlan->without(ViewOperationInterface::class);

        $this->assertCount(2, $objectPlan);
    }
}
