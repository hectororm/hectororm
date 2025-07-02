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
use Hector\Query\Select;
use Hector\Query\Statement\Between;
use PHPUnit\Framework\TestCase;

class BetweenTest extends TestCase
{
    public function testGetStatement()
    {
        $between = new Between('foo', 1, 10);
        $binds = new BindParamList();

        $this->assertEquals('foo BETWEEN :_h_0 AND :_h_1', $between->getStatement($binds));
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
        array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $between = new Between('foo', 1, 10);
        $binds = new BindParamList();

        $this->assertEquals('foo BETWEEN :_h_0 AND :_h_1', $between->getStatement($binds, true));
        $this->assertEquals(
            ['_h_0' => 1, '_h_1' => 10],
        array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
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
        $binds = new BindParamList();

        $this->assertEquals(
            '( SELECT * FROM foo WHERE bar = :_h_0 ) BETWEEN :_h_1 AND :_h_2',
            $between->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'qux', '_h_1' => 1, '_h_2' => 10],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
