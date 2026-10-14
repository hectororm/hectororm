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

namespace Hector\Schema\Plan\Operation;

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\OperationInterface;
use Hector\Schema\Plan\Raw;

final class AddCheck implements OperationInterface
{
    private string $expression;

    /**
     * @param string $table
     * @param string $name Explicit constraint name
     * @param Raw|string $expression SQL expression emitted verbatim
     * @throws PlanException
     */
    public function __construct(private string $table, private string $name, Raw|string $expression)
    {
        if ('' === trim($name)) {
            throw new PlanException('A CHECK constraint requires a non-empty name');
        }

        $this->expression = $expression instanceof Raw ? $expression->getExpression() : $expression;
        if ('' === trim($this->expression)) {
            throw new PlanException('A CHECK constraint requires a non-empty SQL expression');
        }
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->table;
    }

    /**
     * Get constraint name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the SQL expression without the enclosing CHECK (...).
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
