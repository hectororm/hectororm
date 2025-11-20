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
use Hector\Query\Statement\Exists;
use PHPUnit\Framework\TestCase;

class ExistsTest extends TestCase
{
    public function testGetStatement(): void
    {
        $exists = new Exists('SELECT 1');
        $binds = new BindParamList();

        $this->assertEquals('EXISTS( SELECT 1 )', $exists->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithEncapsulation(): void
    {
        $exists = new Exists('SELECT 1');
        $binds = new BindParamList();

        $this->assertEquals('EXISTS( SELECT 1 )', $exists->getStatement($binds, true));
        $this->assertEmpty($binds);
    }

    public function testExistsWithStatement(): void
    {
        $exists = new Exists(
            (new Select())
                ->from('foo')
                ->where('bar', '=', 'qux')
        );
        $binds = new BindParamList();

        $this->assertEquals(
            'EXISTS( SELECT * FROM foo WHERE bar = :_h_0 )',
            $exists->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'qux'],
            array_map(fn(BindParam $bind): mixed => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
