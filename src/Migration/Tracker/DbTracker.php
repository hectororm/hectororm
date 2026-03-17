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
use Hector\Connection\Connection;
use Hector\Migration\Exception\MigrationException;
use Hector\Query\QueryBuilder;
use LogicException;
use Throwable;

class DbTracker implements MigrationTrackerInterface
{
    /** @var list<string>|null */
    private ?array $applied = null;

    /**
     * DbTracker constructor.
     *
     * @param Connection $connection
     * @param string $tableName
     * @param bool $autoCreate Automatically create the tracking table if it does not exist
     */
    public function __construct(
        private Connection $connection,
        private string $tableName = 'hector_migrations',
        private bool $autoCreate = true,
    ) {
        if (false === class_exists(QueryBuilder::class)) {
            throw new LogicException(
                'You cannot use "DbTracker" as the "hectororm/query" package is not installed. ' .
                'Try running "composer require hectororm/query".'
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return $this->loadApplied();
    }

    /**
     * @inheritDoc
     *
     * @return ArrayIterator<int, string>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->loadApplied());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->loadApplied());
    }

    /**
     * @inheritDoc
     */
    public function isApplied(string $migrationId): bool
    {
        return in_array($migrationId, $this->loadApplied(), true);
    }

    /**
     * @inheritDoc
     */
    public function markApplied(string $migrationId, ?float $durationMs = null): void
    {
        if (true === $this->isApplied($migrationId)) {
            return;
        }

        $affectedRows = $this->newQueryBuilder()->insert([
            'migration_id' => $migrationId,
            'applied_at' => date('Y-m-d H:i:s'),
            'duration_ms' => $durationMs,
        ]);

        if (1 !== $affectedRows) {
            throw new MigrationException(sprintf('Failed to mark migration "%s" as applied', $migrationId));
        }

        $this->loadApplied();
        $this->applied[] = $migrationId;
    }

    /**
     * @inheritDoc
     */
    public function markReverted(string $migrationId): void
    {
        $this->loadApplied();

        $affectedRows = $this->newQueryBuilder()
            ->where('migration_id', $migrationId)
            ->delete();

        if (1 !== $affectedRows) {
            throw new MigrationException(sprintf('Failed to mark migration "%s" as reverted', $migrationId));
        }

        $this->loadApplied();
        $this->applied = array_values(array_filter(
            $this->applied ?? [],
            fn(string $id) => $id !== $migrationId,
        ));
    }

    /**
     * Create a new QueryBuilder pre-configured with the tracking table.
     *
     * @return QueryBuilder
     */
    private function newQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->connection))->from($this->tableName);
    }

    /**
     * Create the migrations tracking table.
     *
     * @return void
     * @throws MigrationException
     */
    public function createTable(): void
    {
        $quote = $this->connection->getDriverInfo()->getIdentifierQuote();
        $quotedTable = sprintf('%1$s%2$s%1$s', $quote, str_replace($quote, $quote . $quote, $this->tableName));

        $this->connection->execute(sprintf(
            'CREATE TABLE IF NOT EXISTS %s ('
            . 'migration_id VARCHAR(255) NOT NULL PRIMARY KEY, '
            . 'applied_at DATETIME NOT NULL, '
            . 'duration_ms FLOAT NULL'
            . ')',
            $quotedTable,
        ));
    }

    /**
     * Load applied migrations from the database.
     *
     * @return list<string>
     * @throws MigrationException
     */
    private function loadApplied(): array
    {
        if (null !== $this->applied) {
            return $this->applied;
        }

        try {
            return $this->applied = $this->fetchApplied();
        } catch (Throwable) {
            // Table likely doesn't exist — try auto-creating it
            if (false === $this->autoCreate) {
                throw new MigrationException(
                    sprintf(
                        'Migration tracking table "%s" does not exist. '
                        . 'Call createTable() or set autoCreate to true.',
                        $this->tableName,
                    )
                );
            }

            $this->createTable();

            return $this->applied = [];
        }
    }

    /**
     * Fetch applied migration identifiers from the database.
     *
     * @return list<string>
     */
    private function fetchApplied(): array
    {
        /** @var list<string> $applied */
        $applied = iterator_to_array(
            $this->newQueryBuilder()
                ->column('migration_id')
                ->orderBy('applied_at')
                ->orderBy('migration_id')
                ->fetchColumn(),
            false,
        );

        return $applied;
    }
}
