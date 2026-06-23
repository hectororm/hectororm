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

namespace Hector\Schema\Plan;

use Stringable;

/**
 * Raw SQL expression for a column default value.
 *
 * Wrap a value in this class to have it emitted verbatim in the
 * generated DEFAULT clause instead of being quoted as a string
 * literal. This is required for SQL function calls and keywords such
 * as CURRENT_TIMESTAMP(), NOW() or UUID().
 *
 * Use a plain string for literal defaults; reserve this wrapper for
 * actual SQL expressions. It is not intended to express a NULL
 * default (use a null value with hasDefault enabled instead).
 */
final class Raw implements Stringable
{
    /**
     * Raw constructor.
     *
     * @param string $expression SQL expression emitted verbatim
     */
    public function __construct(private string $expression)
    {
    }

    /**
     * Get the raw SQL expression.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->expression;
    }
}
