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
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\Operation\AddCheck;
use Hector\Schema\Plan\Operation\DropCheck;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\Raw;
use PHPUnit\Framework\TestCase;

class CheckOperationTest extends TestCase
{
    public static function providerExpressions(): array
    {
        return [['price >= 0'], [new Raw('price >= 0')], ['0'], [new Raw('0')], [" price >= 0 /* comment */\n"]];
    }

    /**
     * @dataProvider providerExpressions
     */
    public function testFluentCreateAndAlterPreserveExpressions(Raw|string $expression): void
    {
        foreach ([new CreateTable('products'), new AlterTable('products')] as $table) {
            self::assertSame($table, $table->addCheck('chk_price', $expression));
            $operation = $table->getArrayCopy()[0];

            self::assertInstanceOf(AddCheck::class, $operation);
            self::assertSame('products', $operation->getObjectName());
            self::assertSame('chk_price', $operation->getName());
            self::assertSame((string)$expression, $operation->getExpression());
            self::assertNotInstanceOf(PreOperationInterface::class, $operation);
            self::assertNotInstanceOf(PostOperationInterface::class, $operation);
        }
    }

    public function testFluentDropIsAStructureOperation(): void
    {
        $table = new AlterTable('products');
        self::assertSame($table, $table->dropCheck('chk_price'));
        $operation = $table->getArrayCopy()[0];

        self::assertInstanceOf(DropCheck::class, $operation);
        self::assertSame('products', $operation->getObjectName());
        self::assertSame('chk_price', $operation->getName());
        self::assertNotInstanceOf(PreOperationInterface::class, $operation);
        self::assertNotInstanceOf(PostOperationInterface::class, $operation);
    }

    public static function providerInvalidAddInputs(): array
    {
        return [
            ['', 'price >= 0'],
            [" \t\n", 'price >= 0'],
            ['chk_price', ''],
            ['chk_price', " \t\n"],
            ['chk_price', new Raw('')],
            ['chk_price', new Raw(" \t\n")],
        ];
    }

    /**
     * @dataProvider providerInvalidAddInputs
     */
    public function testAddRejectsEmptyInputsEvenWhenConstructedDirectly(string $name, Raw|string $expression): void
    {
        $this->expectException(PlanException::class);
        new AddCheck('products', $name, $expression);
    }

    public static function providerEmptyNames(): array
    {
        return [[''], [" \t\n"]];
    }

    /**
     * @dataProvider providerEmptyNames
     */
    public function testDropRejectsEmptyNamesEvenWhenConstructedDirectly(string $name): void
    {
        $this->expectException(PlanException::class);
        new DropCheck('products', $name);
    }
}
