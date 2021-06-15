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
use Hector\Query\Statement\Exists;
use PHPUnit\Framework\TestCase;

class ExistsTest extends TestCase
{
    public function testGetStatement()
    {
        $exists = new Exists('SELECT 1');
        $binding = [];

        $this->assertEquals('EXISTS( SELECT 1 )', $exists->getStatement($binding));
        $this->assertEquals([], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $exists = new Exists('SELECT 1');
        $binding = [];

        $this->assertEquals('EXISTS( SELECT 1 )', $exists->getStatement($binding, true));
        $this->assertEquals([], $binding);
    }

    public function testExistsWithStatement()
    {
        $exists = new Exists(
            (new Select())
                ->from('foo')
                ->where('bar', '=', 'qux')
        );
        $binding = [];

        $this->assertEquals(
            'EXISTS( SELECT * FROM foo WHERE bar = ? )',
            $exists->getStatement($binding)
        );
        $this->assertEquals(['qux'], $binding);
    }
}
