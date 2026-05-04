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

use ArrayIterator;
use Hector\Migration\Exception\MigrationException;

class ChainTracker implements MigrationTrackerInterface
{
    /**
     * ChainTracker constructor.
     *
     * @param MigrationTrackerInterface[] $trackers At least one tracker is required
     * @param string $strategy One of ChainStrategy::ANY, ChainStrategy::ALL, ChainStrategy::FIRST
     *
     * @throws MigrationException If no trackers are provided
     */
    public function __construct(
        private array $trackers,
        private string $strategy = ChainStrategy::ANY,
    ) {
        if ([] === $this->trackers) {
            throw new MigrationException('ChainTracker requires at least one tracker');
        }
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return array_values(
            match ($this->strategy) {
                ChainStrategy::FIRST => $this->trackers[0]->getArrayCopy(),
                ChainStrategy::ANY => array_unique(array_merge(...array_map(
                    fn(MigrationTrackerInterface $tracker): array => $tracker->getArrayCopy(),
                    $this->trackers,
                ))),
                ChainStrategy::ALL => array_intersect(...array_map(
                    fn(MigrationTrackerInterface $tracker): array => $tracker->getArrayCopy(),
                    $this->trackers,
                )),
                default => throw new MigrationException(sprintf('Unexpected strategy "%s"', $this->strategy)),
            },
        );
    }

    /**
     * @inheritDoc
     *
     * @return ArrayIterator<int, string>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function isApplied(string $migrationId): bool
    {
        return match ($this->strategy) {
            ChainStrategy::FIRST => $this->trackers[0]->isApplied($migrationId),
            ChainStrategy::ANY => (bool)array_filter(
                $this->trackers,
                fn(MigrationTrackerInterface $tracker): bool => $tracker->isApplied($migrationId),
            ),
            ChainStrategy::ALL => empty(array_filter(
                $this->trackers,
                fn(MigrationTrackerInterface $tracker): bool => false === $tracker->isApplied($migrationId),
            )),
            default => throw new MigrationException(sprintf('Unexpected strategy "%s"', $this->strategy)),
        };
    }

    /**
     * @inheritDoc
     */
    public function markApplied(string $migrationId, ?float $durationMs = null): void
    {
        array_walk(
            $this->trackers,
            fn(MigrationTrackerInterface $tracker) => $tracker->markApplied($migrationId, $durationMs),
        );
    }

    /**
     * @inheritDoc
     */
    public function markReverted(string $migrationId): void
    {
        array_walk(
            $this->trackers,
            fn(MigrationTrackerInterface $tracker) => $tracker->markReverted($migrationId),
        );
    }
}
