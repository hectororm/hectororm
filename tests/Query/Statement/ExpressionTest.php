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

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Statement\Expression;
use Hector\Query\Statement\Quoted;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class ExpressionTest extends TestCase
{
    private function createCapabilities(string $quote): DriverCapabilities
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('getIdentifierQuote')->willReturn($quote);

        return $capabilities;
    }

    public function testStringsOnly(): void
    {
        $expression = new Expression('foo', ' = ', 'bar');
        $binds = new BindParamList();

        $this->assertSame('foo = bar', $expression->getStatement($binds));
    }

    public function testWithQuotedIdentifiers(): void
    {
        $expression = new Expression(
            new Quoted('main.id'),
            ' = ',
            new Quoted('orders.user_id'),
        );
        $binds = new BindParamList();

        $this->assertSame(
            '`main`.`id` = `orders`.`user_id`',
            $expression->getStatement($binds)
        );
    }

    public function testWithPostgreSQLCapabilities(): void
    {
        $expression = new Expression(
            new Quoted('main.id'),
            ' = ',
            new Quoted('orders.user_id'),
        );
        $binds = new BindParamList();

        $this->assertSame(
            '"main"."id" = "orders"."user_id"',
            $expression->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testMixedParts(): void
    {
        $expression = new Expression(
            new Quoted('t.column'),
            ' IS NOT NULL',
        );
        $binds = new BindParamList();

        $this->assertSame(
            '`t`.`column` IS NOT NULL',
            $expression->getStatement($binds)
        );
    }

    public function testNullPartReturnsNull(): void
    {
        $raw = $this->createMock(\Hector\Query\StatementInterface::class);
        $raw->method('getStatement')->willReturn(null);

        $expression = new Expression(
            new Quoted('t.col'),
            ' = ',
            $raw,
        );
        $binds = new BindParamList();

        $this->assertNull($expression->getStatement($binds));
    }

    public function testEmptyReturnsNull(): void
    {
        $expression = new Expression();
        $binds = new BindParamList();

        $this->assertNull($expression->getStatement($binds));
    }

    public function testSingleQuotedPart(): void
    {
        $expression = new Expression(new Quoted('foo'));
        $binds = new BindParamList();

        $this->assertSame('`foo`', $expression->getStatement($binds));
    }

    public function testWithRawStatement(): void
    {
        $expression = new Expression(
            new Quoted('t.col'),
            ' = ',
            new Raw('NOW()'),
        );
        $binds = new BindParamList();

        $this->assertSame(
            '`t`.`col` = NOW()',
            $expression->getStatement($binds)
        );
    }
}
