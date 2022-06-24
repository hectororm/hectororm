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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Statement\NotBetween;
use PHPUnit\Framework\TestCase;

class NotBetweenTest extends TestCase
{
    public function testGetStatement()
    {
        $between = new NotBetween('foo', 1, 10);
        $binds = new BindParamList();

        $this->assertEquals('foo NOT BETWEEN :_h_0 AND :_h_1', $between->getStatement($binds));
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $between = new NotBetween('foo', 1, 10);
        $binds = new BindParamList();

        $this->assertEquals('foo NOT BETWEEN :_h_0 AND :_h_1', $between->getStatement($binds, true));
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
