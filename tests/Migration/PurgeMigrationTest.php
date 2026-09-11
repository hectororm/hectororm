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

namespace Hector\Migration\Tests;

use Hector\Connection\Connection;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;
use Hector\Migration\MigrationRunner;
use Hector\Migration\Provider\ArrayProvider;
use Hector\Migration\Tracker\FileTracker;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

class PurgeMigrationTest extends TestCase
{
    private ?Connection $connection = null;
    private ?string $trackingFile = null;

    public static function purgeModes(): array
    {
        return [
            'SQLite delete' => ['sqlite', false],
            'SQLite reset' => ['sqlite', true],
            'MySQL delete' => ['mysql', false],
            'MySQL truncate' => ['mysql', true],
        ];
    }

    private function connect(string $driver, bool $autoIncrement = true): Connection
    {
        $dsn = 'sqlite::memory:';
        if ('mysql' === $driver) {
            $dsn = getenv('MYSQL_DSN');
            if (false === $dsn || '' === $dsn) {
                $this->markTestSkipped('MYSQL_DSN is required for MySQL/MariaDB purge integration tests');
            }
        }

        $connection = $this->connection = new Connection($dsn);
        if ('sqlite' === $driver) {
            $connection->execute('PRAGMA foreign_keys = ON');
        }

        $id = 'mysql' === $driver
            ? 'INT PRIMARY KEY AUTO_INCREMENT'
            : 'INTEGER PRIMARY KEY' . ($autoIncrement ? ' AUTOINCREMENT' : '');
        $connection->execute('CREATE TABLE hector_purge_items (id ' . $id . ', reference VARCHAR(255) NULL)');
        $connection->execute('INSERT INTO hector_purge_items (id, reference) VALUES (42, NULL)');

        return $connection;
    }

    protected function tearDown(): void
    {
        if (null !== $this->trackingFile && file_exists($this->trackingFile)) {
            unlink($this->trackingFile);
        }

        if (null === $this->connection) {
            return;
        }

        $this->connection->rollBack();
        foreach (['children', 'audit', 'items', 'tracking'] as $suffix) {
            $this->connection->execute('DROP TABLE IF EXISTS hector_purge_' . $suffix);
        }
    }

    private function runner(Connection $connection, Plan $plan, ?Schema $schema = null): MigrationRunner
    {
        $migration = new class ($plan) implements MigrationInterface {
            public function __construct(private Plan $source)
            {
            }

            public function up(Plan $plan): void
            {
                foreach ($this->source as $operation) {
                    $plan->add($operation);
                }
            }
        };

        $this->trackingFile = tempnam(sys_get_temp_dir(), 'hector_purge_');
        unlink($this->trackingFile);

        return new MigrationRunner(
            new ArrayProvider(['purge' => $migration]),
            new FileTracker($this->trackingFile),
            new AutoCompiler($connection),
            $connection,
            $schema,
        );
    }

    private function assertFailed(MigrationRunner $runner, string $message): void
    {
        try {
            $runner->up();
            $this->fail('Expected the migration to fail');
        } catch (MigrationException $exception) {
            $this->assertStringContainsStringIgnoringCase($message, $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }

        $this->assertSame(['purge' => false], $runner->getStatus());
    }

    /** @dataProvider purgeModes */
    public function testPurgeBeforeNotNullAlteration(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $generator = 'sqlite' === $driver ? new Sqlite($connection) : new MySQL($connection);
        $schemaName = 'sqlite' === $driver
            ? 'main'
            : $connection->fetchOne('SELECT DATABASE() AS name')['name'];
        $schema = $generator->generateSchema($schemaName);
        $plan = new Plan();
        $plan->purge('hector_purge_items', resetIncrement: $reset);
        $plan->alter('hector_purge_items')->modifyColumn('reference', 'VARCHAR(255)', nullable: false);
        $runner = $this->runner($connection, $plan, $schema);

        $this->assertSame(['purge'], $runner->up());
        $this->assertSame([], $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $this->assertFalse(
            $generator->generateSchema($schemaName)->getTable('hector_purge_items')
                ->getColumn('reference')->isNullable(),
        );
        $this->assertSame(['purge' => true], $runner->getStatus());
        $this->assertFalse($connection->inTransaction());
    }

    /** @dataProvider purgeModes */
    public function testCounterAndEmptyTable(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $plan = (new Plan())->purge('hector_purge_items', resetIncrement: $reset);
        // Purging twice also exercises an already empty table.
        $plan->purge('hector_purge_items', resetIncrement: $reset);
        $this->runner($connection, $plan)->up();
        $connection->execute("INSERT INTO hector_purge_items (reference) VALUES ('new')");

        $this->assertSame(
            $reset ? 1 : 43,
            (int)$connection->fetchOne('SELECT id FROM hector_purge_items')['id'],
        );
    }

    /** @dataProvider purgeModes */
    public function testDryRunLeavesDataCounterAndTrackingUnchanged(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $runner = $this->runner($connection, (new Plan())->purge('hector_purge_items', $reset));

        $this->assertSame(['purge'], $runner->up(dryRun: true));
        $this->assertSame(['purge' => false], $runner->getStatus());
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $connection->execute("INSERT INTO hector_purge_items (reference) VALUES ('new')");
        $this->assertSame(43, (int)$connection->fetchOne(
            "SELECT id FROM hector_purge_items WHERE reference = 'new'",
        )['id']);
    }

    /** @dataProvider purgeModes */
    public function testFailureAfterPurgeReflectsTransactionSemantics(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $plan = (new Plan())->purge('hector_purge_items', $reset);
        $plan->raw('SELECT * FROM hector_purge_missing');
        $this->assertFailed($this->runner($connection, $plan), 'hector_purge_missing');

        $committed = 'mysql' === $driver && $reset;
        $this->assertCount($committed ? 0 : 1, $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $connection->execute("INSERT INTO hector_purge_items (reference) VALUES ('new')");
        $this->assertSame($committed ? 1 : 43, (int)$connection->fetchOne(
            "SELECT id FROM hector_purge_items WHERE reference = 'new'",
        )['id']);
        $this->assertFalse($connection->inTransaction());
    }

    public function testMySQLDeleteIsCommittedBeforeFailingAlter(): void
    {
        $connection = $this->connect('mysql');
        $plan = (new Plan())->purge('hector_purge_items');
        $plan->alter('hector_purge_items')->addColumn('id', 'INT', nullable: true);
        $this->assertFailed($this->runner($connection, $plan), 'id');
        $this->assertSame([], $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $this->assertFalse($connection->inTransaction());
    }

    /** @dataProvider purgeModes */
    public function testForeignKeyFailureStopsFollowingStatements(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $connection->execute('CREATE TABLE hector_purge_children (' .
            'item_id INT, FOREIGN KEY (item_id) REFERENCES hector_purge_items (id))');
        $connection->execute('INSERT INTO hector_purge_children (item_id) VALUES (42)');
        $plan = (new Plan())->purge('hector_purge_items', $reset);
        $plan->raw("UPDATE hector_purge_items SET reference = 'unexpected'");
        $this->assertFailed($this->runner($connection, $plan), 'foreign key');

        $this->assertNull($connection->fetchOne('SELECT reference FROM hector_purge_items')['reference']);
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM hector_purge_children'));
    }

    /** @dataProvider purgeModes */
    public function testDeleteTriggerFollowsNativeSemantics(string $driver, bool $reset): void
    {
        $connection = $this->connect($driver);
        $connection->execute('CREATE TABLE hector_purge_audit (item_id INT)');
        $body = 'INSERT INTO hector_purge_audit (item_id) VALUES (OLD.id);';
        $connection->execute('CREATE TRIGGER hector_purge_deleted AFTER DELETE ON hector_purge_items ' .
            'FOR EACH ROW ' . ('sqlite' === $driver ? 'BEGIN ' . $body . ' END' : $body));
        $this->runner($connection, (new Plan())->purge('hector_purge_items', $reset))->up();

        $this->assertCount(
            'mysql' === $driver && $reset ? 0 : 1,
            $connection->fetchAll('SELECT * FROM hector_purge_audit'),
        );
    }

    public function testSqliteMissingSequenceRollsBackDelete(): void
    {
        $connection = $this->connect('sqlite', autoIncrement: false);
        $plan = (new Plan())->purge('hector_purge_items', resetIncrement: true);
        $plan->raw("UPDATE hector_purge_items SET reference = 'unexpected'");
        $this->assertFailed($this->runner($connection, $plan), 'sqlite_sequence');
        $this->assertCount(1, $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $this->assertNull($connection->fetchOne('SELECT reference FROM hector_purge_items')['reference']);
    }

    public function testSqliteDeleteWithoutSequence(): void
    {
        $connection = $this->connect('sqlite', autoIncrement: false);
        $this->runner($connection, (new Plan())->purge('hector_purge_items'))->up();
        $this->assertSame([], $connection->fetchAll('SELECT * FROM hector_purge_items'));
    }

    public function testSqliteResetDoesNotAffectOtherSequences(): void
    {
        $connection = $this->connect('sqlite', autoIncrement: false);
        $connection->execute('CREATE TABLE hector_purge_audit (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        $connection->execute('INSERT INTO hector_purge_audit (id) VALUES (99)');
        $this->runner($connection, (new Plan())->purge('hector_purge_items', resetIncrement: true))->up();

        $this->assertSame([], $connection->fetchAll('SELECT * FROM hector_purge_items'));
        $this->assertSame(99, (int)$connection->fetchOne(
            "SELECT seq FROM sqlite_sequence WHERE name = 'hector_purge_audit'",
        )['seq']);
    }
}
