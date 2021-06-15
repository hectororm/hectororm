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

namespace Hector\Query\Tests\Statement;

use Hector\Query\Select;
use Hector\Query\Statement\Between;
use PHPUnit\Framework\TestCase;

class BetweenTest extends TestCase
{
    public function testGetStatement()
    {
        $between = new Between('foo', 1, 10);
        $binding = [];

        $this->assertEquals('foo BETWEEN ? AND ?', $between->getStatement($binding));
        $this->assertEquals([1, 10], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $between = new Between('foo', 1, 10);
        $binding = [];

        $this->assertEquals('foo BETWEEN ? AND ?', $between->getStatement($binding, true));
        $this->assertEquals([1, 10], $binding);
    }

    public function testBetweenWithStatement()
    {
        $between = new Between(
            (new Select())
                ->from('foo')
                ->where('bar', '=', 'qux'),
            1,
            10
        );
        $binding = [];

        $this->assertEquals(
            '( SELECT * FROM foo WHERE bar = ? ) BETWEEN ? AND ?',
            $between->getStatement($binding)
        );
        $this->assertEquals(['qux', 1, 10], $binding);
    }
}
