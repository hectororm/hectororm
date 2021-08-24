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

namespace Hector\Query\Tests\Clause;

use Hector\Query\Clause\Where;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WhereTest extends TestCase
{
    public function testResetWhere()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();

        $this->assertEmpty($clause->where->getStatement($binding));

        $clause->where('foo', 'bar');

        $this->assertNotEmpty($clause->where->getStatement($binding));

        $clause->resetWhere();

        $this->assertEmpty($clause->where->getStatement($binding));
    }

    public function testWhere()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->where('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithoutArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $clause->resetWhere();
        $clause->andWhere();
    }

    public function testAndWhereWithOneArgument()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithTwoConditions()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo');
        $clause->andWhere('bar');

        $this->assertEquals(
            'foo AND bar',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithTwoArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo', 'bar');

        $this->assertEquals(
            'foo = ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testAndWhereWithThreeArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrWhereWithoutArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $clause->resetWhere();
        $clause->orWhere();
    }

    public function testOrWhereWithTwoConditions()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo');
        $clause->orWhere('bar');

        $this->assertEquals(
            'foo OR bar',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrWhereWithOneArgument()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrWhereWithTwoArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo', 'bar');

        $this->assertEquals(
            'foo = ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrWhereWithThreeArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testWhereEquals()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereEquals(
            [
                'EXISTS(corge)',
                'foo' => 'bar',
                'baz' => ['qux', 'quux']
            ]
        );

        $this->assertEquals(
            'EXISTS(corge) AND foo = ? AND baz IN ( ?, ? )',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'qux', 'quux'], $binding);
    }

    public function testWhereIn()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereIn('foo', ['bar', 'baz', 'qux', 'foo']);

        $this->assertEquals(
            'foo IN ( ?, ?, ?, ? )',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz', 'qux', 'foo'], $binding);
    }

    public function testWhereNotIn()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo NOT IN ( ?, ? )',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz'], $binding);
    }

    public function testWhereNull()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereNull('foo');

        $this->assertEquals(
            'foo IS NULL',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testWhereNotNull()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotNull('foo');

        $this->assertEquals(
            'foo IS NOT NULL',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testWhereBetween()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereBetween('foo', 1, 10);

        $this->assertEquals(
            'foo BETWEEN ? AND ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testWhereNotBetween()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotBetween('foo', 1, 10);

        $this->assertEquals(
            'foo NOT BETWEEN ? AND ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testWhereGreaterThan()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereGreaterThan('foo', 10);

        $this->assertEquals(
            'foo > ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereGreaterThanOrEqual()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereGreaterThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo >= ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereLessThan()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereLessThan('foo', 10);

        $this->assertEquals(
            'foo < ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereLessThanOrEqual()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereLessThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo <= ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereExists()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereExists('foo');

        $this->assertEquals(
            'EXISTS( foo )',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testWhereNotExists()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotExists('foo');

        $this->assertEquals(
            'NOT EXISTS( foo )',
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testWhereContains()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereContains('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['%bar%'], $binding);
    }

    public function testWhereStartsWith()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereStartsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar%'], $binding);
    }

    public function testWhereEndsWith()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binding = [];
        $clause->resetWhere();
        $clause->whereEndsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['%bar'], $binding);
    }
}
