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

use Hector\Query\Update;
use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $update = new Update();
        $binding = [];

        $this->assertNull($update->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithoutAssignment()
    {
        $update = new Update();
        $binding = [];
        $update->from('foo');

        $this->assertNull($update->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithOneAssignment()
    {
        $update = new Update();
        $binding = [];
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            'UPDATE' . PHP_EOL .
            '    foo' . PHP_EOL .
            'SET' . PHP_EOL .
            '    bar = ?' . PHP_EOL,
            $update->getStatement($binding)
        );
        $this->assertEquals(['value_bar'], $binding);
    }

    public function testGetStatementWithTwoAssignment()
    {
        $update = new Update();
        $binding = [];
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');

        $this->assertEquals(
            'UPDATE' . PHP_EOL .
            '    foo' . PHP_EOL .
            'SET' . PHP_EOL .
            '    bar = ?,' . PHP_EOL .
            '    baz = ?' . PHP_EOL,
            $update->getStatement($binding)
        );
        $this->assertEquals(['value_bar', 'value_baz'], $binding);
    }

    public function testGetStatementWithConditions()
    {
        $update = new Update();
        $binding = [];
        $update->from('foo');
        $update->assign('bar', 'value_bar');
        $update->assign('baz', 'value_baz');
        $update
            ->where('foo.foo_column', '=', 1)
            ->orWhere('foo.foo_column IS NULL');

        $this->assertEquals(
            'UPDATE' . PHP_EOL .
            '    foo' . PHP_EOL .
            'SET' . PHP_EOL .
            '    bar = ?,' . PHP_EOL .
            '    baz = ?' . PHP_EOL .
            'WHERE' . PHP_EOL .
            '    foo.foo_column = ?' . PHP_EOL .
            '    OR foo.foo_column IS NULL' . PHP_EOL,
            $update->getStatement($binding)
        );
        $this->assertEquals(['value_bar', 'value_baz', 1], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $update = new Update();
        $binding = [];
        $update->from('foo');
        $update->assign('bar', 'value_bar');

        $this->assertEquals(
            '(' . PHP_EOL .
            '    UPDATE' . PHP_EOL .
            '        foo' . PHP_EOL .
            '    SET' . PHP_EOL .
            '        bar = ?' . PHP_EOL .
            ')',
            $update->getStatement($binding, true)
        );
        $this->assertEquals(['value_bar'], $binding);
    }
}
