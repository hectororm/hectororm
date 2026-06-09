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
use Stringable;

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

    /**
     * @return iterable<string, array{string|Stringable, int, string[]}>
     */
    public function providePaths(): iterable
    {
        yield 'bare segment' => ['column', PHP_INT_MAX, ['column']];
        yield 'qualified' => ['table.column', PHP_INT_MAX, ['table', 'column']];
        yield 'three segments' => ['schema.table.column', PHP_INT_MAX, ['schema', 'table', 'column']];
        yield 'empty string' => ['', PHP_INT_MAX, ['']];
        yield 'trailing dot' => ['table.', PHP_INT_MAX, ['table', '']];
        yield 'leading dot' => ['.column', PHP_INT_MAX, ['', 'column']];

        // Quotes are preserved and dots inside a closed pair are not separators
        yield 'backtick segments' => ['`table`.`column`', PHP_INT_MAX, ['`table`', '`column`']];
        yield 'double-quote segments' => ['"table"."column"', PHP_INT_MAX, ['"table"', '"column"']];
        yield 'dot inside backticks' => ['`a.b`.`c`', PHP_INT_MAX, ['`a.b`', '`c`']];
        yield 'dot inside double quotes' => ['"a.b".c', PHP_INT_MAX, ['"a.b"', 'c']];
        yield 'mixed quoted and bare' => ['`main`.column', PHP_INT_MAX, ['`main`', 'column']];
        yield 'backtick wrapping dots only' => ['`a.b.c`', PHP_INT_MAX, ['`a.b.c`']];

        // Wildcard is treated as a plain segment (neutral)
        yield 'wildcard segment' => ['table.*', PHP_INT_MAX, ['table', '*']];

        // Limit behaves like explode()
        yield 'limit 1' => ['a.b.c', 1, ['a.b.c']];
        yield 'limit 2' => ['a.b.c', 2, ['a', 'b.c']];
        yield 'limit 2 keeps quoted dot in remainder' => ['a.`b.c`.d', 2, ['a', '`b.c`.d']];
        yield 'limit above segment count' => ['a.b', 5, ['a', 'b']];
        yield 'limit zero' => ['a.b.c', 0, ['a.b.c']];
        yield 'negative limit' => ['a.b.c', -1, ['a.b.c']];

        // Stringable input
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'table.column';
            }
        };
        yield 'Stringable input' => [$stringable, PHP_INT_MAX, ['table', 'column']];
    }

    /**
     * @dataProvider providePaths
     *
     * @param string[] $expected
     */
    public function testExplodePath(string|Stringable $path, int $limit, array $expected): void
    {
        $this->assertSame($expected, Helper::explodePath($path, $limit));
    }

    /**
     * @return iterable<string, array{string, string, string[]}>
     */
    public function provideQuotedPaths(): iterable
    {
        // Only backtick is a quote: double quotes are inert, dots inside them split
        yield 'backtick only, double-quoted dot splits' => ['`a.b`."c.d"', '`', ['`a.b`', '"c', 'd"']];
        yield 'backtick only, backtick dot kept' => ['`a.b`.c', '`', ['`a.b`', 'c']];

        // Only double quote is a quote: backticks are inert, dots inside them split
        yield 'double quote only, double-quoted dot kept' => ['"a.b".c', '"', ['"a.b"', 'c']];
        yield 'double quote only, backtick dot splits' => ['`a.b`.c', '"', ['`a', 'b`', 'c']];

        // Quote awareness disabled: every dot is a separator
        yield 'no quotes, backtick dot splits' => ['`a.b`.c', '', ['`a', 'b`', 'c']];
        yield 'no quotes, double-quote dot splits' => ['"a.b".c', '', ['"a', 'b"', 'c']];

        // Custom quote character (square bracket style is not symmetric, but single char works)
        yield 'custom single quote char' => ["'a.b'.c", "'", ["'a.b'", 'c']];
    }

    /**
     * @dataProvider provideQuotedPaths
     *
     * @param string[] $expected
     */
    public function testExplodePathWithCustomQuotes(string $path, string $quotes, array $expected): void
    {
        $this->assertSame($expected, Helper::explodePath($path, PHP_INT_MAX, $quotes));
    }
}
