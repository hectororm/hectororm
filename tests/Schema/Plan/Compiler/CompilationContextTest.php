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

namespace Hector\Schema\Tests\Plan\Compiler;

use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Class CompilationContextTest.
 */
class CompilationContextTest extends TestCase
{
    public function testDefaults(): void
    {
        $context = new CompilationContext();

        $this->assertNull($context->schema);
        $this->assertFalse($context->foreignKeyChecksManaged);
    }

    public function testHoldsSchemaAndFlag(): void
    {
        $schema = new Schema(connection: 'default', name: 'mydb', charset: 'utf8mb4');

        $context = new CompilationContext($schema, true);

        $this->assertSame($schema, $context->schema);
        $this->assertTrue($context->foreignKeyChecksManaged);
    }

    public function testWithForeignKeyChecksManagedReturnsNewInstance(): void
    {
        $schema = new Schema(connection: 'default', name: 'mydb', charset: 'utf8mb4');
        $context = new CompilationContext($schema, false);

        $updated = $context->withForeignKeyChecksManaged(true);

        // Immutability: a new instance is returned, the original is untouched.
        $this->assertNotSame($context, $updated);
        $this->assertFalse($context->foreignKeyChecksManaged);
        $this->assertTrue($updated->foreignKeyChecksManaged);

        // The schema is carried over.
        $this->assertSame($schema, $updated->schema);
    }
}
