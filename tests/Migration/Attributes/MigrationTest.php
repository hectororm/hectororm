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

namespace Hector\Migration\Tests\Attributes;

use Hector\Migration\Attributes\Migration;
use Hector\Migration\Tests\Fake\DescribedMigration;
use Hector\Migration\Tests\Fake\Psr4\CreateUsers;
use Hector\Migration\Tests\Fake\Psr4\NoAttribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MigrationTest extends TestCase
{
    public function testDefaults(): void
    {
        $attr = new Migration();

        $this->assertNull($attr->description);
    }

    public function testWithDescription(): void
    {
        $attr = new Migration(description: 'Create users');

        $this->assertSame('Create users', $attr->description);
    }

    public function testReadFromReflection(): void
    {
        $ref = new ReflectionClass(CreateUsers::class);
        $attributes = $ref->getAttributes(Migration::class);

        $this->assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();

        $this->assertSame('Create the users table', $attr->description);
    }

    public function testAbsentAttribute(): void
    {
        $ref = new ReflectionClass(NoAttribute::class);
        $attributes = $ref->getAttributes(Migration::class);

        $this->assertCount(0, $attributes);
    }

    public function testDescriptionOnly(): void
    {
        $ref = new ReflectionClass(DescribedMigration::class);
        $attributes = $ref->getAttributes(Migration::class);

        $this->assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();

        $this->assertSame('A described migration', $attr->description);
    }
}
