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

use Hector\Query\Component\Join;
use Hector\Query\Select;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class JoinTest extends TestCase
{
    public function testGetStatement()
    {
        $join = new Join();
        $binding = [];

        $this->assertNull($join->getStatement($binding));
    }

    public function testJoinOne()
    {
        $join = new Join();
        $join->join(Join::INNER_JOIN, 'bar', null);
        $binding = [];

        $this->assertEquals(
            'INNER JOIN bar',
            $join->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testJoinTwo()
    {
        $join = new Join();
        $join->join(Join::INNER_JOIN, 'bar', null);
        $join->join(Join::LEFT_JOIN, 'baz', 'q.id = baz.id');
        $binding = [];

        $this->assertEquals(
            'INNER JOIN bar LEFT JOIN baz ON ( q.id = baz.id )',
            $join->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testJoinStatement()
    {
        $join = new Join();
        $join->join(
            Join::LEFT_JOIN,
            (new Select())->from('`foo`')
        );
        $binding = [];

        $this->assertEquals(
            'LEFT JOIN ( SELECT * FROM `foo` )',
            $join->getStatement($binding)
        );
        $this->assertEquals([], $binding);
    }

    public function testJoinRawStatement()
    {
        $join = new Join();
        $join->join(
            Join::LEFT_JOIN,
            '`foo`',
            new Raw('UNIX_TIMESTAMP(?)', [$date = date('Y-m-d H:i:s')])
        );
        $binding = [];

        $this->assertEquals(
            'LEFT JOIN `foo` ON ( UNIX_TIMESTAMP(?) )',
            $join->getStatement($binding)
        );
        $this->assertEquals([$date], $binding);
    }
}
