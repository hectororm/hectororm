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

namespace Hector\Query\Tests\Statement;

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Statement\AbstractArithmetic;
use Hector\Query\Statement\Decrement;
use Hector\Query\Statement\Increment;
use Hector\Query\StatementInterface;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

class ArithmeticTest extends TestCase
{
    public function testIncrementDefaultAmount(): void
    {
        $binds = new BindParamList();

        $this->assertSame('stock + :_h_0', (new Increment())->getAssignmentStatement('stock', $binds));
        $this->assertSame(['_h_0' => 1], $this->bindValues($binds));
    }

    public function testDecrementDefaultAmount(): void
    {
        $binds = new BindParamList();

        $this->assertSame('stock - :_h_0', (new Decrement())->getAssignmentStatement('stock', $binds));
        $this->assertSame(['_h_0' => 1], $this->bindValues($binds));
    }

    public function testKeepsRenderedColumnAsIs(): void
    {
        $binds = new BindParamList();

        $this->assertSame(
            '`p`.`stock` - :_h_0',
            (new Decrement(5))->getAssignmentStatement('`p`.`stock`', $binds)
        );
    }

    public function amountProvider(): array
    {
        return [
            'int' => [5, PDO::PARAM_INT],
            'zero' => [0, PDO::PARAM_INT],
            'negative int' => [-3, PDO::PARAM_INT],
            'float' => [1.5, PDO::PARAM_STR],
            'negative float' => [-0.25, PDO::PARAM_STR],
            'numeric string' => ['0.10', PDO::PARAM_STR],
            'negative numeric string' => ['-12.345', PDO::PARAM_STR],
            'exponent string' => ['1e3', PDO::PARAM_STR],
        ];
    }

    /**
     * @dataProvider amountProvider
     */
    public function testAcceptedAmounts(int|float|string $amount, int $expectedType): void
    {
        foreach ([new Increment($amount), new Decrement($amount)] as $arithmetic) {
            $binds = new BindParamList();
            $arithmetic->getAssignmentStatement('stock', $binds);

            $this->assertSame($amount, $arithmetic->getAmount());
            $this->assertSame(['_h_0' => $amount], $this->bindValues($binds));
            $this->assertSame($expectedType, $binds->getArrayCopy()['_h_0']->getDataType());
        }
    }

    public function invalidAmountProvider(): array
    {
        return [
            'NAN' => [NAN],
            'INF' => [INF],
            '-INF' => [-INF],
            'empty string' => [''],
            'non numeric string' => ['abc'],
            'partially numeric string' => ['5 apples'],
            'leading whitespace' => [' 5'],
            'trailing whitespace' => ['5 '],
        ];
    }

    /**
     * @dataProvider invalidAmountProvider
     */
    public function testRejectsInvalidAmounts(float|string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Increment($amount);
    }

    /**
     * @dataProvider invalidAmountProvider
     */
    public function testDecrementRejectsInvalidAmounts(float|string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Decrement($amount);
    }

    public function testRejectsEmptyColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Increment())->getAssignmentStatement(' ', new BindParamList());
    }

    public function testIsAssignmentValueOnly(): void
    {
        $this->assertInstanceOf(AbstractArithmetic::class, new Increment());
        $this->assertNotInstanceOf(StatementInterface::class, new Increment());
        $this->assertNotInstanceOf(StatementInterface::class, new Decrement());
    }

    private function bindValues(BindParamList $binds): array
    {
        return array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy());
    }
}
