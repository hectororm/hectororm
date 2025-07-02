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

namespace Hector\Orm\Tests\Attributes;

use Hector\Orm\Attributes\OrderBy;
use PHPUnit\Framework\TestCase;

class OrderByTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new OrderBy('foo', 'ASC');

        $this->assertEquals('foo', $attribute->column);
        $this->assertEquals('ASC', $attribute->order);
    }

    public function testConstructWithoutOrder()
    {
        $attribute = new OrderBy('foo');

        $this->assertEquals('foo', $attribute->column);
        $this->assertNull($attribute->order);
    }
}
