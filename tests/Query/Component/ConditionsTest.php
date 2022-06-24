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

namespace Hector\Query\Tests\Component;

use BackedEnum;
use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Component\Conditions;
use Hector\Query\Select;
use Hector\Query\Statement\Row;
use Hector\Query\Tests\FakeEnum;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    public function testCount()
    {
        $condition = new Conditions();

        $this->assertCount(0, $condition);

        $condition->equals(['foo' => 'bar', 'baz' => 'qux']);
        $condition->equal('quux', 'foo');

        $this->assertCount(3, $condition);
    }

    public function testNoCondition()
    {
        $condition = new Conditions();
        $binds = new BindParamList();

        $this->assertNull($condition->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testOneCondition()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IS NULL',
            $condition->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testTwoConditions()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $condition->add('`bar`', '=', 'test', Conditions::LINK_OR);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IS NULL OR `bar` = :_h_0',
            $condition->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEncapsulation()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $condition->add('`bar`', '=', 'test', Conditions::LINK_OR);
        $binds = new BindParamList();

        $this->assertEquals(
            '( foo IS NULL OR `bar` = :_h_0 )',
            $condition->getStatement($binds, true)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEqual()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', 'bar');
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEqualWithNullValue()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', null);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IS NULL',
            $conditions->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testEqualWithArray()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', [1, 2]);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IN ( :_h_0, :_h_1 )',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 2],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEqualWithObjectStringable()
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'foo';
            }
        };

        $conditions = new Conditions();
        $conditions->equal('foo', $obj);
        $conditions->equal('bar', [$obj, $obj]);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0 AND bar IN ( :_h_1, :_h_2 )',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => $obj, '_h_1' => $obj, '_h_2' => $obj],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEqualWithObjectNotStringable()
    {
        $obj = new class {
            public string $foo = 'foo';
            public string $bar = 'bar';
        };

        $conditions = new Conditions();
        $conditions->equal('foo', $obj);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo = :_h_0',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => $obj],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEqualWithArrayOfEnum()
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $conditions = new Conditions();
        $conditions->equal('foo', [FakeEnum::FOO, FakeEnum::BAR]);
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IN ( :_h_0, :_h_1 )',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => FakeEnum::FOO->value, '_h_1' => FakeEnum::BAR->value],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testEquals()
    {
        $othersConditions = new Conditions();
        $othersConditions->add(new Row('corge', 'grault'), 'IN', [[1, 2], [3, 4]]);
        $binds = new BindParamList();

        $condition = new Conditions();
        $condition->equals(
            [
                'baz IS NOT NULL',
                'qux' => ['value', 'value2'],
                'quux' => 1,
                $othersConditions,
                'garply' => (new Select())->column('waldo')->from('table')->where('fred', '=', 'test')
            ]
        );

        $this->assertEquals(
            'baz IS NOT NULL ' .
            'AND qux IN ( :_h_0, :_h_1 ) ' .
            'AND quux = :_h_2 ' .
            'AND (corge, grault) IN ( (:_h_3, :_h_4), (:_h_5, :_h_6) ) ' .
            'AND garply IN ( SELECT waldo FROM table WHERE fred = :_h_7 )',
            $condition->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 'value',
                '_h_1' => 'value2',
                '_h_2' => 1,
                '_h_3' => 1,
                '_h_4' => 2,
                '_h_5' => 3,
                '_h_6' => 4,
                '_h_7' => 'test'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testConditionsWithStatement()
    {
        $condition = new Conditions();
        $condition->add(
            'foo',
            'IN',
            (new Select())
                ->from('bar')
                ->where('bar.qux', '=', 1)
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'foo IN ( SELECT * FROM bar WHERE bar.qux = :_h_0 )',
            $condition->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 1],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testConditionsWithRow()
    {
        $conditions = new Conditions();
        $conditions->add(new Row('foo', 'bar'), 'IN', [[1, 2], [3, 4], [5, 6]]);
        $binds = new BindParamList();

        $this->assertEquals(
            '(foo, bar) IN ( (:_h_0, :_h_1), (:_h_2, :_h_3), (:_h_4, :_h_5) )',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            [
                '_h_0' => 1,
                '_h_1' => 2,
                '_h_2' => 3,
                '_h_3' => 4,
                '_h_4' => 5,
                '_h_5' => 6],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testConditionsWithCallback()
    {
        $nbCallbackCalled = 0;
        $select = new Select();
        $conditions = $select->where;
        $binds = new BindParamList();

        $conditions->add(
            function ($select) use (&$nbCallbackCalled) {
                $nbCallbackCalled++;

                $select->where('foo', 'bar');
            }
        );

        $this->assertEquals(
            '( foo = :_h_0 )',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
        $this->assertEquals(1, $nbCallbackCalled);
    }

    public function testConditionsWithValueCallback()
    {
        $nbCallbackCalled = 0;
        $select = new Select();
        $conditions = $select->where;
        $binds = new BindParamList();

        $conditions->add(
            'foo',
            '=',
            function () use (&$nbCallbackCalled) {
                $nbCallbackCalled++;

                return 'bar';
            }
        );

        $this->assertEquals(
            'foo = :_h_0',
            $conditions->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
        $this->assertEquals(1, $nbCallbackCalled);
    }
}
