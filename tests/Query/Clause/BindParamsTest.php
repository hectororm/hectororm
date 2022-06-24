<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Tests\Clause;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Clause\BindParams;
use PHPUnit\Framework\TestCase;

class BindParamsTest extends TestCase
{
    public function testResetBindParams()
    {
        $clause = $this->getMockForTrait(BindParams::class);

        $clause->resetBindParams();
        $bindParams = $clause->getBindParams();
        $clause->resetBindParams();

        $this->assertNotSame($bindParams, $clause->getBindParams());
    }

    public function testGetBindParams()
    {
        $clause = $this->getMockForTrait(BindParams::class);

        $clause->resetBindParams();

        $this->assertInstanceOf(BindParamList::class, $clause->getBindParams());
        $this->assertSame(
            $clause->getBindParams(),
            $clause->getBindParams(),
        );
    }

    public function testBind()
    {
        $clause = $this->getMockForTrait(BindParams::class);
        $clause->resetBindParams();

        $this->assertCount(0, $clause->getBindParams());

        $clause->bind('test', 'value');

        $this->assertCount(1, $clause->getBindParams());
    }
}
