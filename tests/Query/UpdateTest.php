<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Tests;

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\Statement\Decrement;
use Hector\Query\Statement\Encapsulated;
use Hector\Query\Statement\Increment;
use Hector\Query\Statement\Quoted;
use Hector\Query\Update;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{
    public function testGetStatementEmpty(): void
    {
        $update = new Update();
        $binds = new BindParamList();

        $this->assertNull($update->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');

        $this->assertNull($update->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithOneAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithTwoAssignment(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0, baz = :_h_1',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'value_bar',
                '_h_1' => 'value_baz'
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithConditions(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');
        $update
            ->where('foo.foo_column', '=', 1)
            ->orWhere('foo.foo_column IS NULL');

        $this->assertEquals(
            'UPDATE foo SET bar = :_h_0, baz = :_h_1 WHERE foo.foo_column = :_h_2 OR foo.foo_column IS NULL',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'value_bar',
                '_h_1' => 'value_baz',
                '_h_2' => 1
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithArithmeticAssignments(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('products');
        $update->assigns([
            'stock' => new Decrement(5),
            'reserved' => new Increment(5),
            'updated_at' => '2026-10-17 12:00:00',
        ]);
        $update
            ->where('id', 42)
            ->where('stock', '>=', 5);

        $this->assertEquals(
            'UPDATE products SET stock = stock - :_h_0, reserved = reserved + :_h_1, updated_at = :_h_2 ' .
            'WHERE id = :_h_3 AND stock >= :_h_4',
            $update->getStatement($binds)
        );
        $this->assertSame(
            [
                '_h_0' => 5,
                '_h_1' => 5,
                '_h_2' => '2026-10-17 12:00:00',
                '_h_3' => 42,
                '_h_4' => 5,
            ],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithArithmeticAssignmentAndOrderLimit(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('products');
        $update->assign('stock', new Increment('0.10'));
        $update->where('stock', '<', 10);
        $update->orderBy('id');
        $update->limit(1);

        $this->assertEquals(
            'UPDATE products SET stock = stock + :_h_0 WHERE stock < :_h_1 ORDER BY id LIMIT 1',
            $update->getStatement($binds)
        );
        $this->assertSame(
            ['_h_0' => '0.10', '_h_1' => 10],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function quotedArithmeticProvider(): array
    {
        return [
            'backtick' => ['`', '`p`.`stock` = `p`.`stock` - :_h_0'],
            'double quote' => ['"', '"p"."stock" = "p"."stock" - :_h_0'],
        ];
    }

    /**
     * @dataProvider quotedArithmeticProvider
     */
    public function testGetStatementWithQuotedArithmeticColumn(string $quote, string $expected): void
    {
        $driverInfo = $this->createMock(DriverInfo::class);
        $driverInfo->method('getIdentifierQuote')->willReturn($quote);

        $update = new Update();
        $binds = new BindParamList();
        $update->from('products');
        $update->assigns([[new Quoted('p.stock'), new Decrement(1)]]);

        $this->assertEquals('UPDATE products SET ' . $expected, $update->getStatement($binds, $driverInfo));
    }

    public function testGetStatementWithAlreadyQuotedArithmeticColumn(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('products');
        $update->assigns(['`stock`' => new Increment(2)]);

        $this->assertEquals(
            'UPDATE products SET `stock` = `stock` + :_h_0',
            $update->getStatement($binds)
        );
    }

    public function testArithmeticValueRejectedInCondition(): void
    {
        $update = new Update();
        $update->from('products');
        $update->assign('stock', 0);
        $update->where('stock', new Increment());

        $this->expectException(InvalidArgumentException::class);

        $update->getStatement(new BindParamList());
    }

    public function testGetStatementWithEncapsulation(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            '( UPDATE foo SET bar = :_h_0 )',
            (new Encapsulated($update))->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
