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

use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class RawTest extends TestCase
{
    public function testGetStatement()
    {
        $raw = new Raw('UNIX_TIMESTAMP(?)', [$date = date('Y-m-d H:i:s')]);
        $binding = [];

        $this->assertEquals('UNIX_TIMESTAMP(?)', $raw->getStatement($binding));
        $this->assertEquals([$date], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $raw = new Raw('UNIX_TIMESTAMP(?)', [$date = date('Y-m-d H:i:s')]);
        $binding = [];

        $this->assertEquals('UNIX_TIMESTAMP(?)', $raw->getStatement($binding, true));
        $this->assertEquals([$date], $binding);
    }
}
