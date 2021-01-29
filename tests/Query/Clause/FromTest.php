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

use Hector\Query\Clause\From;
use PHPUnit\Framework\TestCase;

class FromTest extends TestCase
{
    public function testResetFrom()
    {
        $clause = new class {
            use From;
        };
        $binding = [];
        $clause->resetFrom();

        $this->assertEmpty($clause->from->getStatement($binding));

        $clause->from('foo');

        $this->assertNotEmpty($clause->from->getStatement($binding));

        $clause->resetFrom();

        $this->assertEmpty($clause->from->getStatement($binding));
    }

    public function testFrom()
    {
        $clause = new class {
            use From;
        };
        $binding = [];
        $clause->resetFrom();
        $clause->from('foo', 'f');
        $clause->from('baz');

        $this->assertEquals(
            '    foo AS f,' . PHP_EOL .
            '    baz' . PHP_EOL,
            $clause->from->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }
}
