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

namespace Hector\Query\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use InvalidArgumentException;

abstract class AbstractArithmetic implements AssignmentValueInterface
{
    /**
     * AbstractArithmetic constructor.
     *
     * @param int|float|string $amount Integer, finite float or numeric string (preserves DECIMAL precision)
     *
     * @throws InvalidArgumentException If amount is not a finite number or a numeric string
     */
    public function __construct(private int|float|string $amount = 1)
    {
        if (is_float($amount) && false === is_finite($amount)) {
            throw new InvalidArgumentException('Arithmetic amount must be a finite number');
        }

        if (is_string($amount) && (false === is_numeric($amount) || trim($amount) !== $amount)) {
            throw new InvalidArgumentException(sprintf('Arithmetic amount "%s" is not a numeric string', $amount));
        }
    }

    /**
     * Get SQL arithmetic operator.
     *
     * @return string
     */
    abstract protected function getOperator(): string;

    /**
     * Get amount.
     *
     * @return int|float|string
     */
    public function getAmount(): int|float|string
    {
        return $this->amount;
    }

    /**
     * @inheritDoc
     */
    public function getAssignmentStatement(
        string $column,
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): string {
        if ('' === trim($column)) {
            throw new InvalidArgumentException('Arithmetic assignment requires a target column');
        }

        return sprintf('%s %s :%s', $column, $this->getOperator(), $bindParams->add($this->amount)->getName());
    }
}
