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
        $binding = [];

        $this->assertNull($condition->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testOneCondition()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $binding = [];

        $this->assertEquals(
            'foo IS NULL',
            $condition->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testTwoConditions()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $condition->add('`bar`', '=', 'test', Conditions::LINK_OR);
        $binding = [];

        $this->assertEquals(
            'foo IS NULL OR `bar` = ?',
            $condition->getStatement($binding)
        );
        $this->assertEquals(['test'], $binding);
    }

    public function testEncapsulation()
    {
        $condition = new Conditions();
        $condition->add('foo IS NULL');
        $condition->add('`bar`', '=', 'test', Conditions::LINK_OR);
        $binding = [];

        $this->assertEquals(
            '( foo IS NULL OR `bar` = ? )',
            $condition->getStatement($binding, true)
        );
        $this->assertEquals(['test'], $binding);
    }

    public function testEqual()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', 'bar');
        $binding = [];

        $this->assertEquals(
            'foo = ?',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            ['bar'],
            $binding
        );
    }

    public function testEqualWithNullValue()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', null);
        $binding = [];

        $this->assertEquals(
            'foo IS NULL',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [],
            $binding
        );
    }

    public function testEqualWithArray()
    {
        $conditions = new Conditions();
        $conditions->equal('foo', [1, 2]);
        $binding = [];

        $this->assertEquals(
            'foo IN ( ?, ? )',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [1, 2],
            $binding
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
        $binding = [];

        $this->assertEquals(
            'foo = ? AND bar IN ( ?, ? )',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [$obj, $obj, $obj],
            $binding
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
        $binding = [];

        $this->assertEquals(
            'foo = ?',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [$obj],
            $binding
        );
    }

    public function testEqualWithArrayOfEnum()
    {
        if (!interface_exists(BackedEnum::class)) {
            $this->markTestSkipped('Enum are not available on this PHP version.');
        }

        $conditions = new Conditions();
        $conditions->equal('foo', [FakeEnum::FOO, FakeEnum::BAR]);
        $binding = [];

        $this->assertEquals(
            'foo IN ( ?, ? )',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [FakeEnum::FOO, FakeEnum::BAR],
            $binding
        );
    }

    public function testEquals()
    {
        $othersConditions = new Conditions();
        $othersConditions->add(new Row('corge', 'grault'), 'IN', [[1, 2], [3, 4]]);
        $binding = [];

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
            'AND qux IN ( ?, ? ) ' .
            'AND quux = ? ' .
            'AND (corge, grault) IN ( (?, ?), (?, ?) ) ' .
            'AND garply IN ( SELECT waldo FROM table WHERE fred = ? )',
            $condition->getStatement($binding)
        );
        $this->assertEquals(
            ['value', 'value2', 1, 1, 2, 3, 4, 'test'],
            $binding
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
        $binding = [];

        $this->assertEquals(
            'foo IN ( SELECT * FROM bar WHERE bar.qux = ? )',
            $condition->getStatement($binding)
        );
        $this->assertEquals([1], $binding);
    }

    public function testConditionsWithRow()
    {
        $conditions = new Conditions();
        $conditions->add(new Row('foo', 'bar'), 'IN', [[1, 2], [3, 4], [5, 6]]);
        $binding = [];

        $this->assertEquals(
            '(foo, bar) IN ( (?, ?), (?, ?), (?, ?) )',
            $conditions->getStatement($binding)
        );
        $this->assertEquals([1, 2, 3, 4, 5, 6], $binding);
    }

    public function testConditionsWithCallback()
    {
        $nbCallbackCalled = 0;
        $select = new Select();
        $conditions = $select->where;
        $binding = [];

        $conditions->add(
            function ($select) use (&$nbCallbackCalled) {
                $nbCallbackCalled++;

                $select->where('foo', 'bar');
            }
        );

        $this->assertEquals(
            '( foo = ? )',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
        $this->assertEquals(1, $nbCallbackCalled);
    }

    public function testConditionsWithValueCallback()
    {
        $nbCallbackCalled = 0;
        $select = new Select();
        $conditions = $select->where;
        $binding = [];

        $conditions->add(
            'foo',
            '=',
            function () use (&$nbCallbackCalled) {
                $nbCallbackCalled++;

                return 'bar';
            }
        );

        $this->assertEquals(
            'foo = ?',
            $conditions->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
        $this->assertEquals(1, $nbCallbackCalled);
    }
}
