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
use Throwable;

/**
 * Dispatched after a migration has failed and been rolled back.
 *
 * Not stoppable: the failure has already occurred.
 */
class MigrationFailedEvent extends AbstractMigrationEvent
{
    /**
     * MigrationFailedEvent constructor.
     *
     * @param string $migrationId
     * @param MigrationInterface $migration
     * @param string $direction Direction::UP or Direction::DOWN
     * @param Throwable $exception The original exception that caused the failure
     * @param bool $dryRun Whether this is a dry-run execution
     * @param float|null $durationMs Execution time in milliseconds until failure
     */
    public function __construct(
        string $migrationId,
        MigrationInterface $migration,
        string $direction,
        private Throwable $exception,
        bool $dryRun = false,
        private ?float $durationMs = null,
    ) {
        parent::__construct($migrationId, $migration, $direction, $dryRun);
    }

    /**
     * Get the exception that caused the failure.
     *
     * @return Throwable
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get execution duration in milliseconds until failure.
     *
     * @return float|null
     */
    public function getDurationMs(): ?float
    {
        return $this->durationMs;
    }
}
