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
use DateTimeImmutable;
use Hector\Migration\Exception\MigrationException;

abstract class AbstractJsonTracker implements MigrationTrackerInterface
{
    /** @var array<string, array{applied_at: string, duration_ms: float|null}>|null */
    private ?array $data = null;

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return array_keys($this->loadData());
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
        return count($this->loadData());
    }

    /**
     * @inheritDoc
     */
    public function isApplied(string $migrationId): bool
    {
        return true === array_key_exists($migrationId, $this->loadData());
    }

    /**
     * @inheritDoc
     */
    public function markApplied(string $migrationId, ?float $durationMs = null): void
    {
        $data = $this->loadData();

        if (true === array_key_exists($migrationId, $data)) {
            return;
        }

        $data[$migrationId] = [
            'migration_id' => $migrationId,
            'applied_at' => (new DateTimeImmutable())->format('c'),
            'duration_ms' => $durationMs,
        ];

        $this->save($data);
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    public function markReverted(string $migrationId): void
    {
        $data = $this->loadData();
        unset($data[$migrationId]);

        $this->save($data);
        $this->data = $data;
    }

    /**
     * Check if the storage exists.
     *
     * @return bool
     */
    abstract protected function storageExists(): bool;

    /**
     * Read the raw JSON content from the storage.
     *
     * @return string
     * @throws MigrationException
     */
    abstract protected function readStorage(): string;

    /**
     * Write the raw JSON content to the storage.
     *
     * @param string $json
     *
     * @return void
     * @throws MigrationException
     */
    abstract protected function writeStorage(string $json): void;

    /**
     * Load tracking data from the storage.
     *
     * @return array<string, array{applied_at: string, duration_ms: float|null}>
     * @throws MigrationException
     */
    private function loadData(): array
    {
        if (null !== $this->data) {
            return $this->data;
        }

        if (false === $this->storageExists()) {
            return $this->data = [];
        }

        $content = $this->readStorage();
        $data = json_decode($content, true);

        if (false === is_array($data)) {
            throw new MigrationException(
                'Invalid JSON in migration tracking storage'
            );
        }

        /** @var array<string, array{applied_at: string, duration_ms: float|null}> $data */
        return $this->data = array_column($data, null, 'migration_id');
    }

    /**
     * Save tracking data to the storage.
     *
     * @param array<string, array{applied_at: string, duration_ms: float|null}> $data
     *
     * @return void
     * @throws MigrationException
     */
    private function save(array $data): void
    {
        $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false === $json) {
            throw new MigrationException('Failed to encode migration tracking data as JSON');
        }

        $this->writeStorage($json);
    }
}
