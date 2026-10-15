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

namespace Hector\Schema\Tests\Plan\Compiler;

use Hector\Connection\Connection;
use Hector\Connection\Driver\DriverInfo;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;
use PDOException;
use PHPUnit\Framework\TestCase;

class ColumnTextOptionsExecuteTest extends TestCase
{
    private function connection(string $driver): Connection
    {
        $connection = new Connection('sqlite' === $driver ? 'sqlite::memory:' : getenv('MYSQL_DSN'));
        if ('sqlite' !== $driver) {
            $connection->execute('SET NAMES utf8mb4');
        }

        return $connection;
    }

    private function schema(Connection $connection): Schema
    {
        if ('sqlite' === $connection->getDriverInfo()->getDriver()) {
            return (new Sqlite($connection))->generateSchema('main');
        }

        return (new MySQL($connection))->generateSchema($connection->fetchColumn('SELECT DATABASE()')[0]);
    }

    private function execute(Connection $connection, Plan $plan): void
    {
        foreach ([...$plan->getStatements(new AutoCompiler($connection), $this->schema($connection))] as $sql) {
            $connection->execute($sql);
        }
    }

    private function assertDuplicateRejected(Connection $connection, string $table, string $value): void
    {
        try {
            $connection->execute("INSERT INTO `$table` (id) VALUES (?)", [$value]);
            $this->fail('Expected the unique index to reject a duplicate');
        } catch (PDOException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
    }

    public static function drivers(): array
    {
        return [['sqlite'], ['mysql']];
    }

    /** @dataProvider drivers */
    public function testSharedUuidMigrationAndColumnModification(string $driver): void
    {
        $connection = $this->connection($driver);
        $table = 'ht_text_' . bin2hex(random_bytes(4));
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        try {
            $create = new Plan();
            $create->create($table, charset: 'utf8mb4', collation: 'utf8mb4_unicode_ci')
                ->addColumn('id', 'CHAR(36)', charset: 'ascii')
                ->addColumn('label', 'VARCHAR(64)', nullable: true)
                ->addIndex('uq_id', ['id'], Index::UNIQUE);
            $this->execute($connection, $create);
            $connection->execute("INSERT INTO `$table` (id, label) VALUES (?, ?)", [$uuid, 'é😀']);
            $this->assertSame([$uuid], $connection->fetchColumn("SELECT id FROM `$table`"));
            $this->assertSame(['é😀'], $connection->fetchColumn("SELECT label FROM `$table`"));
            $this->assertDuplicateRejected($connection, $table, $uuid);

            $metadata = $this->schema($connection)->getTable($table);
            if ('mysql' === $driver) {
                $this->assertSame('ascii', $metadata->getColumn('id')->getCharset());
                $this->assertSame('utf8mb4', $metadata->getColumn('label')->getCharset());
                $this->assertSame('utf8mb4_unicode_ci', $metadata->getColumn('label')->getCollation());
            }

            $add = new Plan();
            $add->alter($table)->addColumn('alias', 'CHAR(36)', nullable: true, charset: 'ascii');
            $this->execute($connection, $add);
            $modify = new Plan();
            $collation = 'sqlite' === $driver ? 'BINARY' : 'ascii_bin';
            $modify->alter($table)->modifyColumn('id', 'VARCHAR(64)', charset: 'ascii', collation: $collation);
            $this->execute($connection, $modify);
            $metadata = $this->schema($connection)->getTable($table);
            $this->assertSame($collation, $metadata->getColumn('id')->getCollation());
            $this->assertSame([$uuid], $connection->fetchColumn("SELECT id FROM `$table`"));
            $connection->execute("INSERT INTO `$table` (id) VALUES (?)", [strtoupper($uuid)]);
            $this->assertSame(2, (int)$connection->fetchColumn("SELECT COUNT(*) FROM `$table`")[0]);

            // MODIFY is a complete new definition; omitted options are not retained.
            $connection->execute("DELETE FROM `$table` WHERE id = ?", [strtoupper($uuid)]);
            $reset = new Plan();
            $reset->alter($table)->modifyColumn('id', 'VARCHAR(64)');
            $this->execute($connection, $reset);
            $metadata = $this->schema($connection)->getTable($table)->getColumn('id');
            if ('mysql' === $driver) {
                $this->assertSame('utf8mb4', $metadata->getCharset());
                $this->assertSame('utf8mb4_unicode_ci', $metadata->getCollation());
            } else {
                $this->assertNull($metadata->getCollation());
            }
        } finally {
            $connection->execute("DROP TABLE IF EXISTS `$table`");
        }
    }

    /** @dataProvider comparisonModes */
    public function testNativeComparisonAndUniqueIndexSemantics(string $driver, bool $caseSensitive): void
    {
        $connection = $this->connection($driver);
        $table = 'ht_text_' . bin2hex(random_bytes(4));
        $sqlite = 'sqlite' === $driver;
        $collation = $sqlite
            ? ($caseSensitive ? 'BINARY' : 'NOCASE')
            : ($caseSensitive ? 'utf8mb4_bin' : 'utf8mb4_unicode_ci');
        try {
            $plan = new Plan();
            $plan->create($table)->addColumn('id', 'VARCHAR(64)', charset: 'utf8mb4', collation: $collation)
                ->addIndex('uq_id', ['id'], Index::UNIQUE);
            $this->execute($connection, $plan);
            foreach ([['ABC', 'abc', !$caseSensitive], ['e', 'é', !$sqlite && !$caseSensitive],
                ['space', 'space ', !$sqlite]] as [$first, $second, $equal]) {
                $connection->execute("DELETE FROM `$table`");
                $connection->execute("INSERT INTO `$table` (id) VALUES (?)", [$first]);
                $this->assertSame($equal ? [$first] : [],
                    $connection->fetchColumn("SELECT id FROM `$table` WHERE id = ?", [$second]));
                if ($equal) {
                    $this->assertDuplicateRejected($connection, $table, $second);
                } else {
                    $connection->execute("INSERT INTO `$table` (id) VALUES (?)", [$second]);
                    $this->assertSame(2, (int)$connection->fetchColumn("SELECT COUNT(*) FROM `$table`")[0]);
                }
            }
        } finally {
            $connection->execute("DROP TABLE IF EXISTS `$table`");
        }
    }

    public static function comparisonModes(): array
    {
        return [['sqlite', false], ['sqlite', true], ['mysql', false], ['mysql', true]];
    }

    public function testSqliteDoesNotEnforceAsciiOrTranslateNativeCollations(): void
    {
        $connection = $this->connection('sqlite');
        $create = new Plan();
        $create->create('items')->addColumn('id', 'CHAR(36)', charset: 'ascii');
        $this->execute($connection, $create);
        $connection->execute("INSERT INTO items VALUES ('é😀')");
        $this->assertSame(['é😀'], $connection->fetchColumn('SELECT id FROM items'));

        $invalid = new Plan();
        $invalid->create('invalid')->addColumn('id', 'CHAR(36)', charset: 'ascii', collation: 'ascii_bin');
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('no such collation sequence: ascii_bin');
        $this->execute($connection, $invalid);
    }

    /** @dataProvider drivers */
    public function testGeneratedTextColumnsAndLegacyRename(string $driver): void
    {
        $connection = $this->connection($driver);
        $table = 'ht_text_' . bin2hex(random_bytes(4));
        $collation = 'sqlite' === $driver ? 'NOCASE' : 'ascii_general_ci';
        try {
            $create = new Plan();
            $create->create($table)->addColumn('id', 'CHAR(36)', charset: 'ascii')
                ->addColumn('derived', 'VARCHAR(36)', generated: new Generated('RTRIM(id)', stored: true),
                    charset: 'ascii', collation: $collation);
            $this->execute($connection, $create);
            $connection->execute("INSERT INTO `$table` (id) VALUES ('ABC')");
            $this->assertSame(['ABC'], $connection->fetchColumn("SELECT derived FROM `$table` WHERE derived = 'abc'"));

            if ('sqlite' !== $driver) {
                $schema = $this->schema($connection);
                $engine = $connection->getDriverInfo()->getDriver();
                $version = 'mariadb' === $engine ? '10.5.1' : '5.7.44';
                $compiler = new MySQLCompiler((new DriverInfo($engine, $version))->getCapabilities());
                $rename = new Plan();
                $rename->alter($table)->renameColumn('derived', 'renamed');
                foreach ($rename->getStatements($compiler, $schema) as $statement) {
                    $connection->execute($statement);
                }
                $column = $this->schema($connection)->getTable($table)->getColumn('renamed');
                $this->assertSame('ascii', $column->getCharset());
                $this->assertSame($collation, $column->getCollation());
                $this->assertTrue($column->isGeneratedStored());
                $this->assertSame(['ABC'], $connection->fetchColumn("SELECT renamed FROM `$table` WHERE renamed = 'abc'"));
            }
        } finally {
            $connection->execute("DROP TABLE IF EXISTS `$table`");
        }
    }
}
