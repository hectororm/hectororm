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

namespace Hector\Migration\Event;

use Hector\Migration\MigrationInterface;

/**
 * Dispatched after a migration has been successfully executed.
 *
 * Not stoppable: the migration has already been applied/reverted.
 */
class MigrationAfterEvent extends AbstractMigrationEvent
{
    /**
     * MigrationAfterEvent constructor.
     *
     * @param string $migrationId
     * @param MigrationInterface $migration
     * @param string $direction Direction::UP or Direction::DOWN
     * @param bool $dryRun Whether this is a dry-run execution
     * @param float|null $durationMs Execution time in milliseconds
     */
    public function __construct(
        string $migrationId,
        MigrationInterface $migration,
        string $direction,
        bool $dryRun = false,
        private ?float $durationMs = null,
    ) {
        parent::__construct($migrationId, $migration, $direction, $dryRun);
    }

    /**
     * Get execution duration in milliseconds.
     *
     * @return float|null
     */
    public function getDurationMs(): ?float
    {
        return $this->durationMs;
    }
}
