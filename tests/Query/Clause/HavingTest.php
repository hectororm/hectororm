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

use Hector\Query\Clause\Having;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HavingTest extends TestCase
{
    public function testResetHaving()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();

        $this->assertEmpty($clause->having->getStatement($binding));

        $clause->having('foo', 'bar');

        $this->assertNotEmpty($clause->having->getStatement($binding));

        $clause->resetHaving();

        $this->assertEmpty($clause->having->getStatement($binding));
    }

    public function testHaving()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->having('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndHavingWithoutArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $clause->resetHaving();
        $clause->andHaving();
    }

    public function testAndHavingWithOneArgument()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->andHaving('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndHavingWithTwoConditions()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->andHaving('foo');
        $clause->andHaving('bar');

        $this->assertEquals(
            'foo AND bar',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndHavingWithTwoArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->andHaving('foo', 'bar');

        $this->assertEquals(
            'foo = ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testAndHavingWithThreeArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->andHaving('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrHavingWithoutArgument()
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $clause->resetHaving();
        $clause->orHaving();
    }

    public function testOrHavingWithTwoConditions()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->orHaving('foo');
        $clause->orHaving('bar');

        $this->assertEquals(
            'foo OR bar',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrHavingWithOneArgument()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->orHaving('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrHavingWithTwoArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->orHaving('foo', 'bar');

        $this->assertEquals(
            'foo = ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrHavingWithThreeArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->orHaving('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testHavingEquals()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingEquals(
            [
                'EXISTS(corge)',
                'foo' => 'bar',
                'baz' => ['qux', 'quux']
            ]
        );

        $this->assertEquals(
            'EXISTS(corge) AND foo = ? AND baz IN ( ?, ? )',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar', 'qux', 'quux'], $binding);
    }

    public function testHavingIn()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo IN ( ?, ? )',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz'], $binding);
    }

    public function testHavingNotIn()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo NOT IN ( ?, ? )',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz'], $binding);
    }

    public function testHavingNull()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingNull('foo');

        $this->assertEquals(
            'foo IS NULL',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testHavingNotNull()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotNull('foo');

        $this->assertEquals(
            'foo IS NOT NULL',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testHavingBetween()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingBetween('foo', 1, 10);

        $this->assertEquals(
            'foo BETWEEN ? AND ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testHavingNotBetween()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotBetween('foo', 1, 10);

        $this->assertEquals(
            'foo NOT BETWEEN ? AND ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testHavingGreaterThan()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingGreaterThan('foo', 10);

        $this->assertEquals(
            'foo > ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testHavingGreaterThanOrEqual()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingGreaterThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo >= ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testHavingLessThan()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingLessThan('foo', 10);

        $this->assertEquals(
            'foo < ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testHavingLessThanOrEqual()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingLessThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo <= ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testHavingExists()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingExists('foo');

        $this->assertEquals(
            'EXISTS( foo )',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testHavingNotExists()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotExists('foo');

        $this->assertEquals(
            'NOT EXISTS( foo )',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testHavingContains()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingContains('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['%bar%'], $binding);
    }

    public function testHavingStartsWith()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingStartsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar%'], $binding);
    }

    public function testHavingEndsWith()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binding = [];
        $clause->resetHaving();
        $clause->havingEndsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE ?',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['%bar'], $binding);
    }
}
