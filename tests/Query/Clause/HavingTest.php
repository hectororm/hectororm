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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\Having;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HavingTest extends TestCase
{
    public function testResetHaving()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();

        $this->assertEmpty($clause->having->getStatement($binds));

        $clause->having('foo', 'bar');

        $this->assertNotEmpty($clause->having->getStatement($binds));

        $clause->resetHaving();

        $this->assertEmpty($clause->having->getStatement($binds));
    }

    public function testHaving()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->having('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
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
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->andHaving('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testAndHavingWithTwoConditions()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->andHaving('foo');
        $clause->andHaving('bar');

        $this->assertEquals(
            'foo AND bar',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testAndHavingWithTwoArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->andHaving('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAndHavingWithThreeArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->andHaving('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
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
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->orHaving('foo');
        $clause->orHaving('bar');

        $this->assertEquals(
            'foo OR bar',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrHavingWithOneArgument()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->orHaving('foo');

        $this->assertEquals(
            'foo',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrHavingWithTwoArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->orHaving('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testOrHavingWithThreeArguments()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->orHaving('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingEquals()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingEquals(
            [
                'EXISTS(corge)',
                'foo' => 'bar',
                'baz' => ['qux', 'quux']
            ]
        );

        $this->assertEquals(
            'EXISTS(corge) AND foo = :_h_0 AND baz IN ( :_h_1, :_h_2 )',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar', '_h_1' => 'qux', '_h_2' => 'quux'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingIn()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo IN ( :_h_0, :_h_1 )',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingNotIn()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo NOT IN ( :_h_0, :_h_1 )',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar', '_h_1' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingNull()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingNull('foo');

        $this->assertEquals(
            'foo IS NULL',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingNotNull()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingNotNull('foo');

        $this->assertEquals(
            'foo IS NOT NULL',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingBetween()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingBetween('foo', 1, 10);

        $this->assertEquals(
            'foo BETWEEN :_h_0 AND :_h_1',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingNotBetween()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingNotBetween('foo', 1, 10);

        $this->assertEquals(
            'foo NOT BETWEEN :_h_0 AND :_h_1',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingGreaterThan()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingGreaterThan('foo', 10);

        $this->assertEquals(
            'foo > :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingGreaterThanOrEqual()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingGreaterThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo >= :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingLessThan()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingLessThan('foo', 10);

        $this->assertEquals(
            'foo < :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingLessThanOrEqual()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingLessThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo <= :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingExists()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingExists('foo');

        $this->assertEquals(
            'EXISTS( foo )',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testHavingNotExists()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingNotExists('foo');

        $this->assertEquals(
            'NOT EXISTS( foo )',
            $clause->having->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testHavingContains()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingContains('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => '%bar%'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingStartsWith()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingStartsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar%'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testHavingEndsWith()
    {
        /** @var Having $clause */
        $clause = $this->getMockForTrait(Having::class);
        $binds = new BindParamList();
        $clause->resetHaving();
        $clause->havingEndsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->having->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => '%bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
