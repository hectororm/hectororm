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

namespace Hector\Query\Tests;

use Hector\Query\Select;
use Hector\Query\Union;
use PHPUnit\Framework\TestCase;

class UnionTest extends TestCase
{
    public function testStatementEmpty()
    {
        $binding = [];
        $union = new Union();

        $this->assertNull($union->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testStatement()
    {
        $binding = [];
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union->addSelect($select, $select2);

        $this->assertEquals(
            '(' . PHP_EOL .
            '    SELECT' . PHP_EOL .
            '        *' . PHP_EOL .
            '    FROM' . PHP_EOL .
            '        foo AS f' . PHP_EOL .
            '    WHERE' . PHP_EOL .
            '        bar = ?' . PHP_EOL .
            ')' . PHP_EOL .
            'UNION DISTINCT' . PHP_EOL .
            '(' . PHP_EOL .
            '    SELECT' . PHP_EOL .
            '        *' . PHP_EOL .
            '    FROM' . PHP_EOL .
            '        foo2 AS f' . PHP_EOL .
            '    WHERE' . PHP_EOL .
            '        bar = ?' . PHP_EOL .
            ')' . PHP_EOL,
            $union->getStatement($binding)
        );
        $this->assertEquals(['baz', 'baz'], $binding);
    }

    public function testStatementEncapsulate()
    {
        $binding = [];
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->addSelect($select)
            ->addSelect($select2);

        $this->assertEquals(
            '(' . PHP_EOL .
            '    (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            foo AS f' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar = ?' . PHP_EOL .
            '    )' . PHP_EOL .
            '    UNION DISTINCT' . PHP_EOL .
            '    (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            foo2 AS f' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar = ?' . PHP_EOL .
            '    )' . PHP_EOL .
            ')' . PHP_EOL,
            $union->getStatement($binding, true)
        );
        $this->assertEquals(['baz', 'baz'], $binding);
    }

    public function testStatementUnionAll()
    {
        $binding = [];
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->all()
            ->addSelect($select)
            ->addSelect($select2);

        $this->assertEquals(
            '(' . PHP_EOL .
            '    SELECT' . PHP_EOL .
            '        *' . PHP_EOL .
            '    FROM' . PHP_EOL .
            '        foo AS f' . PHP_EOL .
            '    WHERE' . PHP_EOL .
            '        bar = ?' . PHP_EOL .
            ')' . PHP_EOL .
            'UNION ALL' . PHP_EOL .
            '(' . PHP_EOL .
            '    SELECT' . PHP_EOL .
            '        *' . PHP_EOL .
            '    FROM' . PHP_EOL .
            '        foo2 AS f' . PHP_EOL .
            '    WHERE' . PHP_EOL .
            '        bar = ?' . PHP_EOL .
            ')' . PHP_EOL,
            $union->getStatement($binding)
        );
        $this->assertEquals(['baz', 'baz'], $binding);
    }

    public function testStatementWithOrderAndLimit()
    {
        $binding = [];
        $select = new Select();
        $select->from('foo', 'f')->where('bar', 'baz');
        $select2 = new Select();
        $select2->from('foo2', 'f')->where('bar', 'baz');

        $union = new Union();
        $union
            ->all()
            ->addSelect($select)
            ->addSelect($select2)
        ->limit(10)
        ->orderBy('a');

        $this->assertEquals(
            '(' . PHP_EOL .
            '    (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            foo AS f' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar = ?' . PHP_EOL .
            '    )' . PHP_EOL .
            '    UNION ALL' . PHP_EOL .
            '    (' . PHP_EOL .
            '        SELECT' . PHP_EOL .
            '            *' . PHP_EOL .
            '        FROM' . PHP_EOL .
            '            foo2 AS f' . PHP_EOL .
            '        WHERE' . PHP_EOL .
            '            bar = ?' . PHP_EOL .
            '    )' . PHP_EOL .
            ')' . PHP_EOL .
            'ORDER BY' . PHP_EOL .
            '    a' . PHP_EOL .
            'LIMIT 10' . PHP_EOL,
            $union->getStatement($binding)
        );
        $this->assertEquals(['baz', 'baz'], $binding);
    }
}
