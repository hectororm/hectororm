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

namespace Hector\Migration;

use Hector\Connection\Connection;
use Hector\Migration\Attributes\Migration;
use Hector\Migration\Event\MigrationAfterEvent;
use Hector\Migration\Event\MigrationBeforeEvent;
use Hector\Migration\Event\MigrationFailedEvent;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\Provider\MigrationProviderInterface;
use Hector\Migration\Tracker\MigrationTrackerInterface;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Throwable;

class MigrationRunner
{
    /**
     * MigrationRunner constructor.
     *
     * @param MigrationProviderInterface $provider
     * @param MigrationTrackerInterface $tracker
     * @param CompilerInterface $compiler
     * @param Connection $connection
     * @param Schema|null $schema
     * @param LoggerInterface|null $logger
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(
        private MigrationProviderInterface $provider,
        private MigrationTrackerInterface $tracker,
        private CompilerInterface $compiler,
        private Connection $connection,
        private ?Schema $schema = null,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * Get pending migrations (not yet applied).
     *
     * @return array<string, MigrationInterface> Keyed by migration identifier
     */
    public function getPending(): array
    {
        return array_filter(
            $this->provider->getArrayCopy(),
            fn(MigrationInterface $migration, string $id) => false === $this->tracker->isApplied($id),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Get applied migrations (already executed).
     *
     * @return array<string, MigrationInterface> Keyed by migration identifier
     */
    public function getApplied(): array
    {
        return array_filter(
            $this->provider->getArrayCopy(),
            fn(MigrationInterface $migration, string $id) => true === $this->tracker->isApplied($id),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Get the status of all migrations.
     *
     * @return array<string, bool> Migration identifier => applied (true/false)
     */
    public function getStatus(): array
    {
        $migrations = $this->provider->getArrayCopy();

        return array_combine(
            array_keys($migrations),
            array_map(
                fn(string $id) => $this->tracker->isApplied($id),
                array_keys($migrations),
            ),
        );
    }

    /**
     * Apply pending migrations.
     *
     * @param int|null $steps Maximum number of migrations to apply (null = all)
     * @param bool $dryRun If true, compile SQL and dispatch events but do not execute or track
     *
     * @return string[] List of applied migration identifiers
     * @throws MigrationException
     */
    public function up(?int $steps = null, bool $dryRun = false): array
    {
        $pending = $this->getPending();
        $applied = [];
        $count = 0;

        foreach ($pending as $id => $migration) {
            if (null !== $steps && $count >= $steps) {
                break;
            }

            if (true === $this->executeMigration($id, $migration, Direction::UP, $dryRun)) {
                $applied[] = $id;
                $count++;
            }
        }

        return $applied;
    }

    /**
     * Revert applied migrations.
     *
     * @param int $steps Number of migrations to revert
     * @param bool $dryRun If true, compile SQL and dispatch events but do not execute or track
     *
     * @return string[] List of reverted migration identifiers
     * @throws MigrationException
     */
    public function down(int $steps = 1, bool $dryRun = false): array
    {
        $applied = array_reverse($this->getApplied(), true);
        $reverted = [];
        $count = 0;

        foreach ($applied as $id => $migration) {
            if ($count >= $steps) {
                break;
            }

            if (true === $this->executeMigration($id, $migration, Direction::DOWN, $dryRun)) {
                $reverted[] = $id;
                $count++;
            }
        }

        return $reverted;
    }

    /**
     * Execute a single migration (up or down).
     *
     * @param string $id
     * @param MigrationInterface $migration
     * @param string $direction Direction::UP or Direction::DOWN
     * @param bool $dryRun If true, compile SQL but do not execute or track
     *
     * @return bool True if migration was executed, false if skipped by event listener
     * @throws MigrationException
     */
    private function executeMigration(
        string $id,
        MigrationInterface $migration,
        string $direction,
        bool $dryRun = false,
    ): bool {
        if (false === in_array($direction, [Direction::UP, Direction::DOWN], true)) {
            throw new MigrationException(sprintf('Unexpected direction "%s"', $direction));
        }

        if (Direction::DOWN === $direction && false === $migration instanceof ReversibleMigrationInterface) {
            throw new MigrationException(sprintf('Migration "%s" cannot be reverted', $id));
        }

        $label = $this->buildLabel($id, $migration);
        $prefix = true === $dryRun ? '[DRY-RUN] ' : '';

        // Dispatch before event (stoppable)
        /** @var MigrationBeforeEvent|null $beforeEvent */
        $beforeEvent = $this->eventDispatcher?->dispatch(new MigrationBeforeEvent(
            $id,
            $migration,
            $direction,
            $dryRun
        ));

        if (true === $beforeEvent?->isPropagationStopped()) {
            $this->logger?->debug(sprintf('%sMigration %s skipped by event listener', $prefix, $label));

            return false;
        }

        $this->logger?->info(sprintf(
            '%s%s migration %s...',
            $prefix,
            Direction::UP === $direction ? 'Applying' : 'Reverting',
            $label,
        ));

        $plan = new Plan();

        if (Direction::DOWN === $direction) {
            assert($migration instanceof ReversibleMigrationInterface);
            $migration->down($plan);
        } else {
            $migration->up($plan);
        }

        $startTime = hrtime(true);

        // Plan not empty?
        if (false === $plan->isEmpty()) {
            if (false === $dryRun) {
                $this->connection->beginTransaction();
            }

            try {
                foreach ($plan->getStatements($this->compiler, $this->schema) as $sql) {
                    $this->logger?->debug(sprintf('%s[%s] %s', $prefix, $id, $sql));

                    if (false === $dryRun) {
                        $this->connection->execute($sql);
                    }
                }

                if (false === $dryRun) {
                    $this->connection->commit();
                }
            } catch (Throwable $e) {
                $durationMs = (hrtime(true) - $startTime) / 1e6;

                if (false === $dryRun) {
                    $this->connection->rollBack();
                }

                $this->logger?->error(sprintf(
                    '%sMigration %s failed during %s: %s',
                    $prefix,
                    $label,
                    $direction,
                    $e->getMessage(),
                ));

                $this->eventDispatcher?->dispatch(
                    new MigrationFailedEvent($id, $migration, $direction, $e, dryRun: $dryRun, durationMs: $durationMs)
                );

                throw new MigrationException(
                    message: sprintf('Migration "%s" failed during %s: %s', $id, $direction, $e->getMessage()),
                    code: (int)$e->getCode(),
                    previous: $e,
                );
            }
        }

        $durationMs = (hrtime(true) - $startTime) / 1e6;

        if (false === $dryRun) {
            match ($direction) {
                Direction::UP => $this->tracker->markApplied($id, $durationMs),
                Direction::DOWN => $this->tracker->markReverted($id),
            };
        }

        $this->logger?->info(sprintf(
            '%sMigration %s %s successfully (%.2fms)',
            $prefix,
            $label,
            Direction::UP === $direction ? 'applied' : 'reverted',
            $durationMs,
        ));

        $this->eventDispatcher?->dispatch(
            new MigrationAfterEvent($id, $migration, $direction, dryRun: $dryRun, durationMs: $durationMs)
        );

        return true;
    }

    /**
     * Build a human-readable label for a migration, including description if available.
     *
     * @param string $id
     * @param MigrationInterface $migration
     *
     * @return string
     */
    private function buildLabel(string $id, MigrationInterface $migration): string
    {
        if (null !== ($description = $this->getDescription($migration))) {
            return sprintf('"%s" (%s)', $id, $description);
        }

        return sprintf('"%s"', $id);
    }

    /**
     * Read the description from a migration's #[Migration] attribute.
     *
     * @param MigrationInterface $migration
     *
     * @return string|null
     */
    private function getDescription(MigrationInterface $migration): ?string
    {
        $attributes = (new ReflectionClass($migration))->getAttributes(Migration::class);

        if ([] === $attributes) {
            return null;
        }

        return $attributes[0]->newInstance()->description;
    }
}
