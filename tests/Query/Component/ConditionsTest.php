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

use Hector\Query\Component\Conditions;
use Hector\Query\Select;
use Hector\Query\Statement\Row;
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
            '    foo IS NULL' . PHP_EOL,
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
            '    foo IS NULL' . PHP_EOL .
            '    OR `bar` = ?' . PHP_EOL,
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
            '    (' . PHP_EOL .
            '        foo IS NULL' . PHP_EOL .
            '        OR `bar` = ?' . PHP_EOL .
            '    )' . PHP_EOL,
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
            '    foo = ?' . PHP_EOL,
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
            '    foo IS NULL' . PHP_EOL,
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
            '    foo IN (?, ?)' . PHP_EOL,
            $conditions->getStatement($binding)
        );
        $this->assertEquals(
            [1, 2],
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
            '    baz IS NOT NULL' . PHP_EOL .
            '    AND qux IN (?, ?)' . PHP_EOL .
            '    AND quux = ?' . PHP_EOL .
            '    AND (corge, grault) IN ((?, ?), (?, ?))' . PHP_EOL .
            '    AND garply IN (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            waldo' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            table' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            fred = ?' . PHP_EOL .
            '    )' . PHP_EOL,
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
            '    foo IN (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            bar' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar.qux = ?' . PHP_EOL .
            '    )' . PHP_EOL,
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
            '    (foo, bar) IN ((?, ?), (?, ?), (?, ?))' . PHP_EOL,
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
            '    foo = ?' . PHP_EOL,
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
            '    foo = ?' . PHP_EOL,
            $conditions->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
        $this->assertEquals(1, $nbCallbackCalled);
    }
}
