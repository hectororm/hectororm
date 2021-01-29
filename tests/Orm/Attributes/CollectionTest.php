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

use Hector\Orm\Attributes\Collection as CollectionAttr;
use Hector\Orm\Collection\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

class CollectionTest extends TestCase
{
    public function testConstruct()
    {
        $attribute = new CollectionAttr(Collection::class);

        $this->assertEquals(Collection::class, $attribute->collection);
    }

    public function testConstructWithBadCollection()
    {
        $this->expectException(TypeError::class);

        new CollectionAttr(stdClass::class);
    }
}
