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

use Hector\Query\Clause\Limit;
use PHPUnit\Framework\TestCase;

class LimitTest extends TestCase
{
    public function testResetLimit()
    {
        $clause = new class {
            use Limit;
        };
        $binding = [];
        $clause->resetLimit();

        $this->assertEmpty($clause->limit->getStatement($binding));

        $clause->limit(1);

        $this->assertNotEmpty($clause->limit->getStatement($binding));

        $clause->resetLimit();

        $this->assertEmpty($clause->limit->getStatement($binding));
    }

    public function testLimit()
    {
        $clause = new class {
            use Limit;
        };
        $binding = [];
        $clause->resetLimit();
        $clause->limit(10);

        $this->assertEquals(
            'LIMIT 10',
            $clause->limit->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testLimitWithOffset()
    {
        $clause = new class {
            use Limit;
        };
        $binding = [];
        $clause->resetLimit();
        $clause->limit(10, 5);

        $this->assertEquals(
            'LIMIT 10 OFFSET 5',
            $clause->limit->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOffset()
    {
        $clause = new class {
            use Limit;
        };
        $binding = [];
        $clause->resetLimit();
        $clause->limit(10);
        $clause->offset(5);

        $this->assertEquals(
            'LIMIT 10 OFFSET 5',
            $clause->limit->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testOffsetAlone()
    {
        $clause = new class {
            use Limit;
        };
        $binding = [];
        $clause->resetLimit();
        $clause->offset(5);

        $this->assertNull($clause->limit->getStatement($binding));
        $this->assertEmpty($binding);
    }
}
