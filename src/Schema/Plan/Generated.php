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

use Hector\Schema\Exception\PlanException;

/**
 * SQL expression defining a VIRTUAL (default) or STORED generated column.
 */
final class Generated
{
    /**
     * @param string $expression SQL expression emitted verbatim
     * @param bool $stored Store the computed value instead of computing it on read
     */
    public function __construct(private string $expression, private bool $stored = false)
    {
        if ('' === trim($expression)) {
            throw new PlanException('A generated column requires a non-empty SQL expression');
        }
    }

    /**
     * Get the SQL expression.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Is the generated value stored?
     *
     * @return bool
     */
    public function isStored(): bool
    {
        return $this->stored;
    }
}
