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

/**
 * Raw SQL statement to be executed as-is within a plan.
 *
 * Raw statements bypass the compiler and are emitted verbatim
 * during the structure pass of plan compilation. They are useful
 * for SQL features not covered by the plan API (e.g., fulltext
 * indexes, triggers, engine changes, etc.).
 *
 * An optional driver filter can restrict execution to specific
 * database drivers (e.g., ['mysql', 'mariadb']).
 */
final class RawStatement implements OperationInterface
{
    /**
     * RawStatement constructor.
     *
     * @param string $statement SQL statement
     * @param string[]|null $drivers Driver filter (null = all drivers)
     */
    public function __construct(
        private string $statement,
        private ?array $drivers = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): ?string
    {
        return null;
    }

    /**
     * Get the raw SQL statement.
     *
     * @return string
     */
    public function getStatement(): string
    {
        return $this->statement;
    }

    /**
     * Get the driver filter.
     *
     * Returns null if the statement should be emitted for all drivers,
     * or an array of driver names (e.g., ['mysql', 'mariadb']) to
     * restrict execution.
     *
     * @return string[]|null
     */
    public function getDrivers(): ?array
    {
        return $this->drivers;
    }
}
