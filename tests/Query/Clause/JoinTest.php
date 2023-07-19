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

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\Join;
use PHPUnit\Framework\TestCase;

class JoinTest extends TestCase
{
    public function testResetJoin()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();

        $this->assertEmpty($clause->join->getStatement($binds));

        $clause->innerJoin('foo');

        $this->assertNotEmpty($clause->join->getStatement($binds));

        $clause->resetJoin();

        $this->assertEmpty($clause->join->getStatement($binds));
    }

    public function testInnerJoin()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();
        $clause->innerJoin('foo', 'foo.bar IS NULL');
        $clause->innerJoin('baz');

        $this->assertEquals(
            'INNER JOIN foo ON ( foo.bar IS NULL ) INNER JOIN baz',
            $clause->join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testLeftJoin()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();
        $clause->leftJoin('foo', 'foo.bar IS NULL');
        $clause->leftJoin('baz');

        $this->assertEquals(
            'LEFT JOIN foo ON ( foo.bar IS NULL ) LEFT JOIN baz',
            $clause->join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testRightJoin()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();
        $clause->rightJoin('foo', 'foo.bar IS NULL');
        $clause->rightJoin('baz');

        $this->assertEquals(
            'RIGHT JOIN foo ON ( foo.bar IS NULL ) RIGHT JOIN baz',
            $clause->join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testJoinWithArrayConditions()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();
        $clause->innerJoin('table', ['foo.bar IS NULL', 'baz' => 'qux']);
        $clause->rightJoin('baz');

        $this->assertEquals(
            'INNER JOIN table ON ( foo.bar IS NULL AND baz = qux ) RIGHT JOIN baz',
            $clause->join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testInnerJoinWithAlias()
    {
        $clause = new class {
            use Join;
        };
        $binds = new BindParamList();
        $clause->resetJoin();
        $clause->innerJoin('foo', 'alias.bar IS NULL', 'alias');

        $this->assertEquals(
            'INNER JOIN foo AS `alias` ON ( alias.bar IS NULL )',
            $clause->join->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }
}
