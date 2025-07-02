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

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Component\Join;
use Hector\Query\Select;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class JoinTest extends TestCase
{
    public function testGetStatement()
    {
        $join = new Join();
        $binds = new BindParamList();

        $this->assertNull($join->getStatement($binds));
    }

    public function testJoinOne()
    {
        $join = new Join();
        $join->join(Join::INNER_JOIN, 'bar', null);
        $binds = new BindParamList();

        $this->assertEquals(
            'INNER JOIN bar',
            $join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testJoinTwo()
    {
        $join = new Join();
        $join->join(Join::INNER_JOIN, 'bar', null);
        $join->join(Join::LEFT_JOIN, 'baz', 'q.id = baz.id');
        $binds = new BindParamList();

        $this->assertEquals(
            'INNER JOIN bar LEFT JOIN baz ON ( q.id = baz.id )',
            $join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testJoinStatement()
    {
        $join = new Join();
        $join->join(
            Join::LEFT_JOIN,
            (new Select())->from('`foo`')
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'LEFT JOIN ( SELECT * FROM `foo` )',
            $join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testJoinRawStatement()
    {
        $join = new Join();
        $join->join(
            Join::LEFT_JOIN,
            '`foo`',
            new Raw('UNIX_TIMESTAMP(:_h_0)', [$date = date('Y-m-d H:i:s')])
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'LEFT JOIN `foo` ON ( UNIX_TIMESTAMP(:_h_0) )',
            $join->getStatement($binds)
        );
        $this->assertEquals(
            [$date],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
