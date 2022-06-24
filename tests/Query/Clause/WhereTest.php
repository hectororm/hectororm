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
use Hector\Query\Clause\Where;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WhereTest extends TestCase
{
    public function testResetWhere()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();

        $this->assertEmpty($clause->where->getStatement($binds));

        $clause->where('foo', 'bar');

        $this->assertNotEmpty($clause->where->getStatement($binds));

        $clause->resetWhere();

        $this->assertEmpty($clause->where->getStatement($binds));
    }

    public function testWhere()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->where('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
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
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->andWhere('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testAndWhereWithTwoConditions()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->andWhere('foo');
        $clause->andWhere('bar');

        $this->assertEquals(
            'foo AND bar',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testAndWhereWithTwoArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->andWhere('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testAndWhereWithThreeArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->andWhere('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
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
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->orWhere('foo');
        $clause->orWhere('bar');

        $this->assertEquals(
            'foo OR bar',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrWhereWithOneArgument()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->orWhere('foo');

        $this->assertEquals(
            'foo',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testOrWhereWithTwoArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->orWhere('foo', 'bar');

        $this->assertEquals(
            'foo = :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testOrWhereWithThreeArguments()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->orWhere('foo', '<>', 'bar');

        $this->assertEquals(
            'foo <> :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereEquals()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereEquals(
            [
                'EXISTS(corge)',
                'foo' => 'bar',
                'baz' => ['qux', 'quux']
            ]
        );

        $this->assertEquals(
            'EXISTS(corge) AND foo = :_h_0 AND baz IN ( :_h_1, :_h_2 )',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'bar',
                '_h_1' => 'qux',
                '_h_2' => 'quux'
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereIn()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereIn('foo', ['bar', 'baz', 'qux', 'foo']);

        $this->assertEquals(
            'foo IN ( :_h_0, :_h_1, :_h_2, :_h_3 )',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'bar',
                '_h_1' => 'baz',
                '_h_2' => 'qux',
                '_h_3' => 'foo'
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereNotIn()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo NOT IN ( :_h_0, :_h_1 )',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'bar',
                '_h_1' => 'baz'
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereNull()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereNull('foo');

        $this->assertEquals(
            'foo IS NULL',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereNotNull()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereNotNull('foo');

        $this->assertEquals(
            'foo IS NOT NULL',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereBetween()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereBetween('foo', 1, 10);

        $this->assertEquals(
            'foo BETWEEN :_h_0 AND :_h_1',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 1,
                '_h_1' => 10
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereNotBetween()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereNotBetween('foo', 1, 10);

        $this->assertEquals(
            'foo NOT BETWEEN :_h_0 AND :_h_1',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 1,
                '_h_1' => 10
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereGreaterThan()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereGreaterThan('foo', 10);

        $this->assertEquals(
            'foo > :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 10
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereGreaterThanOrEqual()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereGreaterThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo >= :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 10
            ],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereLessThan()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereLessThan('foo', 10);

        $this->assertEquals(
            'foo < :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereLessThanOrEqual()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereLessThanOrEqual('foo', 10);

        $this->assertEquals(
            'foo <= :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereExists()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereExists('foo');

        $this->assertEquals(
            'EXISTS( foo )',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testWhereNotExists()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereNotExists('foo');

        $this->assertEquals(
            'NOT EXISTS( foo )',
            $clause->where->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testWhereContains()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereContains('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => '%bar%'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereStartsWith()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereStartsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar%'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testWhereEndsWith()
    {
        /** @var Where $clause */
        $clause = $this->getMockForTrait(Where::class);
        $binds = new BindParamList();
        $clause->resetWhere();
        $clause->whereEndsWith('foo', 'bar');

        $this->assertEquals(
            'foo LIKE :_h_0',
            $clause->where->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => '%bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
