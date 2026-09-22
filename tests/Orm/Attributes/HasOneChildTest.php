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

declare(strict_types=1);

namespace Hector\Orm\Tests\Attributes;

use Hector\Orm\Attributes\HasOneChild;
use Hector\Orm\Tests\Fake\Entity\Customer;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

class HasOneChildTest extends TestCase
{
    public function testNamedArgumentsPreserveColumnsAndAllowLifecycleOptions(): void
    {
        $attribute = new HasOneChild(
            target: Customer::class,
            name: 'customer',
            columns: ['id' => 'parent_id'],
            orphanRemoval: true,
            where: ['active' => 1],
        );

        $this->assertSame(Customer::class, $attribute->target);
        $this->assertSame('customer', $attribute->name);
        $this->assertSame(['id' => 'parent_id'], $attribute->columns);
    }

    public function testNonEntityTargetIsRejected(): void
    {
        $this->expectException(TypeError::class);

        new HasOneChild(stdClass::class, 'child');
    }
}
