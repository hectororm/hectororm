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

use DateTimeImmutable;
use Hector\Migration\MigrationInterface;

abstract class AbstractMigrationEvent
{
    private DateTimeImmutable $time;

    /**
     * AbstractMigrationEvent constructor.
     *
     * @param string $migrationId
     * @param MigrationInterface $migration
     * @param string $direction Direction::UP or Direction::DOWN
     * @param bool $dryRun Whether this is a dry-run execution
     */
    public function __construct(
        private string $migrationId,
        private MigrationInterface $migration,
        private string $direction,
        private bool $dryRun = false,
    ) {
        $this->time = new DateTimeImmutable();
    }

    /**
     * Get migration identifier.
     *
     * @return string
     */
    public function getMigrationId(): string
    {
        return $this->migrationId;
    }

    /**
     * Get migration instance.
     *
     * @return MigrationInterface
     */
    public function getMigration(): MigrationInterface
    {
        return $this->migration;
    }

    /**
     * Get direction (Direction::UP or Direction::DOWN).
     *
     * @return string
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    /**
     * Get event time.
     *
     * @return DateTimeImmutable
     */
    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    /**
     * Whether this is a dry-run execution.
     *
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
