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

namespace Hector\Migration\Tracker;

use Countable;
use IteratorAggregate;

/**
 * @extends IteratorAggregate<int, string>
 */
interface MigrationTrackerInterface extends IteratorAggregate, Countable
{
    /**
     * Get all applied migration identifiers as an array.
     *
     * @return string[]
     */
    public function getArrayCopy(): array;

    /**
     * Check if a migration has been applied.
     *
     * @param string $migrationId
     *
     * @return bool
     */
    public function isApplied(string $migrationId): bool;

    /**
     * Mark a migration as applied.
     *
     * @param string $migrationId
     * @param float|null $durationMs Execution time in milliseconds
     *
     * @return void
     */
    public function markApplied(string $migrationId, ?float $durationMs = null): void;

    /**
     * Mark a migration as reverted (remove from applied).
     *
     * @param string $migrationId
     *
     * @return void
     */
    public function markReverted(string $migrationId): void;
}
