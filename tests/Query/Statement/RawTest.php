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
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class RawTest extends TestCase
{
    public function testGetStatement()
    {
        $raw = new Raw('UNIX_TIMESTAMP(?)', [$date = date('Y-m-d H:i:s')]);
        $binds = new BindParamList();

        $this->assertEquals('UNIX_TIMESTAMP(?)', $raw->getStatement($binds));
        $this->assertEquals(
            [$date],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $raw = new Raw('UNIX_TIMESTAMP(?)', [$date = date('Y-m-d H:i:s')]);
        $binds = new BindParamList();

        $this->assertEquals('UNIX_TIMESTAMP(?)', $raw->getStatement($binds, true));
        $this->assertEquals(
            [$date],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
