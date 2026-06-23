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
use Hector\Query\Statement\Quoted;
use LogicException;
use Throwable;

class DbTracker implements MigrationTrackerInterface
{
    /** @var list<string>|null */
    private ?array $applied = null;

    /** @var bool|null Cached presence of the "id" auto-increment column */
    private ?bool $hasIdColumn = null;

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

        // The migration_id column is unique. Using an "ignore duplicates" insert
        // makes this atomic across concurrent processes: it affects 1 row when we win the
        // race (lock acquired) and 0 rows when another process already inserted the same id
        // (treated as already applied — idempotent), without raising on the conflict.
        // The "id" auto-increment column is populated by the database, so it is not set here.
        $affectedRows = $this->newQueryBuilder()
            ->ignore()
            ->insert([
                'migration_id' => $migrationId,
                'applied_at' => gmdate('Y-m-d H:i:s'),
                'duration_ms' => $durationMs,
            ]);

        if (0 === $affectedRows) {
            // Another process inserted it concurrently: refresh the cache from the database.
            $this->applied = $this->fetchApplied();

            return;
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

        // Reverting is idempotent: deleting a migration that is not tracked here affects 0
        // rows, which is fine (e.g. in a ChainTracker where the migration was only tracked by
        // another tracker). Only a real failure would raise, via the query layer.
        $this->newQueryBuilder()
            ->where('migration_id', $migrationId)
            ->delete();

        $this->loadApplied();
        $this->applied = array_values(array_filter(
            $this->applied ?? [],
            fn(string $id): bool => $id !== $migrationId,
        ));
    }

    /**
     * Create a new QueryBuilder pre-configured with the tracking table.
     *
     * @return QueryBuilder
     */
    private function newQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->connection))->from(new Quoted($this->tableName));
    }

    /**
     * Quote the tracking table name for the current driver.
     *
     * @return string
     */
    private function quotedTable(): string
    {
        $quote = $this->connection->getDriverInfo()->getIdentifierQuote();

        return sprintf('%1$s%2$s%1$s', $quote, str_replace($quote, $quote . $quote, $this->tableName));
    }

    /**
     * Create the migrations tracking table.
     *
     * The "id" column is a database auto-increment used to replay migrations in their
     * exact application order; applied_at only has second precision and cannot
     * disambiguate ties. The auto-increment syntax is driver-specific.
     *
     * @return void
     * @throws MigrationException
     */
    public function createTable(): void
    {
        $idColumn = match ($this->connection->getDriverInfo()->getDriver()) {
            'pgsql' => 'id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY',
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            default => 'id INT AUTO_INCREMENT PRIMARY KEY',
        };

        $this->connection->execute(sprintf(
            'CREATE TABLE IF NOT EXISTS %s ('
            . '%s, '
            . 'migration_id VARCHAR(255) NOT NULL UNIQUE, '
            . 'applied_at DATETIME NOT NULL, '
            . 'duration_ms FLOAT NULL'
            . ')',
            $this->quotedTable(),
            $idColumn,
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
     * Orders by the "id" auto-increment column (exact application order) when the
     * tracking table has it. Tables created by a previous version may not, so the
     * fallback orders by "applied_at" then "migration_id" — deterministic, but not
     * necessarily the real application order (that information was never recorded).
     *
     * @return list<string>
     */
    private function fetchApplied(): array
    {
        $builder = $this->newQueryBuilder()->column('migration_id');

        if (true === $this->hasIdColumn()) {
            $builder->orderBy('id');
        } else {
            $builder->orderBy('applied_at')->orderBy('migration_id');
        }

        /** @var list<string> $applied */
        $applied = iterator_to_array($builder->fetchColumn(), false);

        return $applied;
    }

    /**
     * Whether the tracking table has the "id" auto-increment column.
     *
     * Tables created by a previous version of {@see DbTracker} predate the column,
     * so its presence is detected once and cached.
     *
     * @return bool
     */
    private function hasIdColumn(): bool
    {
        if (null !== $this->hasIdColumn) {
            return $this->hasIdColumn;
        }

        try {
            // A zero-row probe: if "id" is unknown, the driver raises and we fall back.
            $this->connection->fetchOne(
                sprintf('SELECT id FROM %s WHERE 1 = 0', $this->quotedTable())
            );

            return $this->hasIdColumn = true;
        } catch (Throwable) {
            return $this->hasIdColumn = false;
        }
    }
}
