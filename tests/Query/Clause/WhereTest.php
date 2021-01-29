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
use PHPUnit\Framework\TestCase;

class WhereTest extends TestCase
{
    public function testResetWhere()
    {
        $clause = new class {
            use Where;
        };
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
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->where('foo');

        $this->assertEquals(
            '    foo' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithoutArgument()
    {
        $this->expectException(\InvalidArgumentException::class);

        $clause = new class {
            use Where;
        };
        $clause->resetWhere();
        $clause->andWhere();
    }

    public function testAndWhereWithOneArgument()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo');

        $this->assertEquals(
            '    foo' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithTwoConditions()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo');
        $clause->andWhere('bar');

        $this->assertEquals(
            '    foo' . PHP_EOL .
            '    AND bar' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testAndWhereWithTwoArguments()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo', 'bar');

        $this->assertEquals(
            '    foo = ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testAndWhereWithThreeArguments()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->andWhere('foo', '<>', 'bar');

        $this->assertEquals(
            '    foo <> ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrWhereWithoutArgument()
    {
        $this->expectException(\InvalidArgumentException::class);

        $clause = new class {
            use Where;
        };
        $clause->resetWhere();
        $clause->orWhere();
    }

    public function testOrWhereWithTwoConditions()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo');
        $clause->orWhere('bar');

        $this->assertEquals(
            '    foo' . PHP_EOL .
            '    OR bar' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrWhereWithOneArgument()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo');

        $this->assertEquals(
            '    foo' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOrWhereWithTwoArguments()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo', 'bar');

        $this->assertEquals(
            '    foo = ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testOrWhereWithThreeArguments()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->orWhere('foo', '<>', 'bar');

        $this->assertEquals(
            '    foo <> ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar'], $binding);
    }

    public function testWhereEquals()
    {
        $clause = new class {
            use Where;
        };
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
            '    EXISTS(corge)' . PHP_EOL .
            '    AND foo = ?' . PHP_EOL .
            '    AND baz IN (?, ?)' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'qux', 'quux'], $binding);
    }

    public function testWhereIn()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereIn('foo', ['bar', 'baz', 'qux', 'foo']);

        $this->assertEquals(
            '    foo IN (?, ?, ?, ?)' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz', 'qux', 'foo'], $binding);
    }

    public function testWhereNotIn()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotIn('foo', ['bar', 'baz']);

        $this->assertEquals(
            '    foo NOT IN (?, ?)' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals(['bar', 'baz'], $binding);
    }

    public function testWhereBetween()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereBetween('foo', 1, 10);

        $this->assertEquals(
            '    foo BETWEEN ? AND ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testWhereNotBetween()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotBetween('foo', 1, 10);

        $this->assertEquals(
            '    foo NOT BETWEEN ? AND ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([1, 10], $binding);
    }

    public function testWhereGreaterThan()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereGreaterThan('foo', 10);

        $this->assertEquals(
            '    foo > ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereGreaterThanOrEqual()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereGreaterThanOrEqual('foo', 10);

        $this->assertEquals(
            '    foo >= ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereLessThan()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereLessThan('foo', 10);

        $this->assertEquals(
            '    foo < ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereLessThanOrEqual()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereLessThanOrEqual('foo', 10);

        $this->assertEquals(
            '    foo <= ?' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEquals([10], $binding);
    }

    public function testWhereExists()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereExists('foo');

        $this->assertEquals(
            '    EXISTS( foo )' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testWhereNotExists()
    {
        $clause = new class {
            use Where;
        };
        $binding = [];
        $clause->resetWhere();
        $clause->whereNotExists('foo');

        $this->assertEquals(
            '    NOT EXISTS( foo )' . PHP_EOL,
            $clause->where->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }
}
