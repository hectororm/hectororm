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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Operation\AddColumn;
use Hector\Schema\Plan\Operation\ModifyColumn;
use PHPUnit\Framework\TestCase;

class GeneratedTest extends TestCase
{
    public function testVirtualByDefaultAndVerbatimExpression(): void
    {
        $expression = " COALESCE(price, 0) * quantity /* retain SQL */ ";
        $generated = new Generated($expression);

        $this->assertSame($expression, $generated->getExpression());
        $this->assertFalse($generated->isStored());
    }

    public function testStored(): void
    {
        $generated = new Generated('price * quantity', stored: true);

        $this->assertTrue($generated->isStored());
        $this->assertSame('price * quantity', $generated->getExpression());
    }

    /**
     * @dataProvider emptyExpressions
     */
    public function testRejectsEmptyExpression(string $expression): void
    {
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('non-empty SQL expression');

        new Generated($expression);
    }

    public static function emptyExpressions(): array
    {
        return [[''], [' '], ["\t\r\n"]];
    }

    public function testZeroIsAValidExpression(): void
    {
        $this->assertSame('0', (new Generated('0'))->getExpression());
    }

    /**
     * @dataProvider columnEntryPoints
     */
    public function testEmptyShorthandUsesGeneratedValidation(string $entryPoint): void
    {
        $this->expectException(PlanException::class);
        $this->expectExceptionMessage('non-empty SQL expression');

        match ($entryPoint) {
            'AddColumn' => new AddColumn('items', 'total', 'INTEGER', generated: " \t\n"),
            'ModifyColumn' => new ModifyColumn('items', 'total', 'INTEGER', generated: " \t\n"),
            'addColumn' => (new AlterTable('items'))->addColumn('total', 'INTEGER', generated: " \t\n"),
            'modifyColumn' => (new AlterTable('items'))->modifyColumn('total', 'INTEGER', generated: " \t\n"),
        };
    }

    public static function columnEntryPoints(): array
    {
        return [['AddColumn'], ['ModifyColumn'], ['addColumn'], ['modifyColumn']];
    }
}
