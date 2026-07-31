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

namespace Hector\Schema\Plan\Compiler;

use Hector\Schema\Schema;

/**
 * Immutable compilation context passed through the compiler and the dialect.
 *
 * Carries the state that used to live as mutable instance properties on the
 * compiler, so that compilation is stateless and reentrant (a dialect can
 * safely recurse — e.g. SQLite table rebuild — without saving/restoring flags).
 */
final class CompilationContext
{
    /**
     * @param Schema|null $schema           Optional schema used to adapt the compilation strategy.
     * @param bool        $foreignKeyChecksManaged Whether the plan explicitly manages FK checks
     *                                        (contains a DisableForeignKeyChecks entry), so internal
     *                                        mechanisms (e.g. SQLite rebuild) skip their own PRAGMA.
     */
    public function __construct(
        public readonly ?Schema $schema = null,
        public readonly bool $foreignKeyChecksManaged = false,
    ) {
    }

    /**
     * Return a copy with the given foreign-key-checks-managed flag.
     *
     * @param bool $managed
     *
     * @return self
     */
    public function withForeignKeyChecksManaged(bool $managed): self
    {
        return new self($this->schema, $managed);
    }
}
