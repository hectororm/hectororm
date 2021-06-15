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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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

        $clause = new class {
            use Having;
        };
        $clause->resetHaving();
        $clause->andHaving();
    }

    public function testAndHavingWithOneArgument()
    {
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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

        $clause = new class {
            use Having;
        };
        $clause->resetHaving();
        $clause->orHaving();
    }

    public function testOrHavingWithTwoConditions()
    {
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            'foo NOT IN ( ?, ? )',
            $clause->having->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz'], $binding);
    }

    public function testHavingBetween()
    {
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
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
        $clause = new class {
            use Having;
        };
        $binding = [];
        $clause->resetHaving();
        $clause->havingNotExists('foo');

        $this->assertEquals(
            'NOT EXISTS( foo )',
            $clause->having->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }
}
