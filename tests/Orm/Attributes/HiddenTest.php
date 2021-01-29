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

use Hector\Orm\Attributes\Hidden;
use PHPUnit\Framework\TestCase;

class HiddenTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new Hidden('foo', 'bar', 'baz');

        $this->assertEquals(['foo', 'bar', 'baz'], $attribute->columns);
    }

    public function testConstructEmpty()
    {
        $attribute = new Hidden();

        $this->assertEquals([], $attribute->columns);
    }
}
