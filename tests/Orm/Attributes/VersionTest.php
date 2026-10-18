<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Orm\Tests\Attributes;

use Hector\Orm\Attributes\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testConstruct(): void
    {
        $attribute = new Version('version');

        $this->assertEquals('version', $attribute->column);
    }
}
