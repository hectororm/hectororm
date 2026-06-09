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

namespace Hector\Query\Tests;

use Hector\Query\Helper;
use Hector\Query\Statement\Quoted;
use Hector\Query\Statement\SqlFunction;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public function provideColumnReferences(): iterable
    {
        // Plain string column references
        yield 'bare column' => ['create_time', true];
        yield 'underscored column' => ['film_id', true];
        yield 'qualified column' => ['main.create_time', true];
        yield 'schema.table.column' => ['sakila.film.film_id', true];
        yield 'backtick-quoted column' => ['`main`.`create_time`', true];
        yield 'double-quoted column' => ['"main"."create_time"', true];
        yield 'mixed quoted/bare segments' => ['`main`.create_time', true];

        // Quoted statement is always a column reference
        yield 'Quoted statement' => [new Quoted('main.create_time'), true];
        yield 'Quoted bare' => [new Quoted('create_time'), true];

        // Expressions / functions are not column references
        yield 'RAND()' => ['RAND()', false];
        yield 'COUNT(*)' => ['COUNT(*)', false];
        yield 'function on column' => ['LOWER(main.title)', false];
        yield 'arithmetic' => ['price + 1', false];
        yield 'column with direction' => ['create_time DESC', false];
        yield 'mismatched quotes' => ['`main"', false];
        yield 'unbalanced quote' => ['`main', false];

        // Non-string, non-Quoted values are not column references
        yield 'SqlFunction statement' => [new SqlFunction('RAND', ''), false];
        yield 'closure' => [fn(): string => 'foo', false];
        yield 'integer' => [42, false];
        yield 'null' => [null, false];
    }

    /**
     * @dataProvider provideColumnReferences
     */
    public function testIsColumnReference(mixed $column, bool $expected): void
    {
        $this->assertSame($expected, Helper::isColumnReference($column));
    }
}
