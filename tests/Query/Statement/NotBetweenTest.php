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

use Hector\Query\Statement\NotBetween;
use PHPUnit\Framework\TestCase;

class NotBetweenTest extends TestCase
{
    public function testGetStatement()
    {
        $between = new NotBetween('foo', 1, 10);
        $binding = [];

        $this->assertEquals('foo NOT BETWEEN ? AND ?', $between->getStatement($binding));
        $this->assertEquals([1, 10], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $between = new NotBetween('foo', 1, 10);
        $binding = [];

        $this->assertEquals('foo NOT BETWEEN ? AND ?', $between->getStatement($binding, true));
        $this->assertEquals([1, 10], $binding);
    }
}
