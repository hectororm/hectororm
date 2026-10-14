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

namespace Hector\Schema\Tests\Generator;

use Hector\Connection\Connection;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\Schema;
use PHPUnit\Framework\TestCase;

class MySQLChecksExecuteTest extends TestCase
{
    private Connection $connection;
    private string $schemaName;
    private array $schemas = [];

    protected function setUp(): void
    {
        $this->connection = new Connection(getenv('MYSQL_DSN'));
        if (false === $this->connection->getDriverInfo()->getCapabilities()->hasCheckConstraints()) {
            $this->markTestSkipped('The server does not enforce CHECK constraints');
        }

        $this->schemaName = 'hector_check_' . bin2hex(random_bytes(4));
        $this->createSchema($this->schemaName);
    }

    protected function tearDown(): void
    {
        foreach ($this->schemas as $schema) {
            $this->connection->execute(sprintf('DROP DATABASE `%s`', $schema));
        }
    }

    private function createSchema(string $schema): void
    {
        $this->connection->execute(sprintf('CREATE DATABASE `%s`', $schema));
        $this->schemas[] = $schema;
    }

    public function testColumnAndTableChecksSurviveIntrospectionAndSerialization(): void
    {
        $this->connection->execute(sprintf(
            'CREATE TABLE `%s`.items (price INT CHECK (price >= 0), starts_at INT, ends_at INT, ' .
            'CONSTRAINT chk_period CHECK (ends_at >= starts_at))',
            $this->schemaName,
        ));
        $schema = (new MySQL($this->connection))->generateSchema($this->schemaName);
        $table = $schema->getTable('items');
        self::assertTrue($table->hasCheckMetadata());
        self::assertCount(2, iterator_to_array($table->getChecks()));
        self::assertTrue($table->getCheck('chk_period')->isEnforced());
        self::assertStringContainsString('ends_at', $table->getCheck('chk_period')->getExpression());
        self::assertStringContainsString('starts_at', $table->getCheck('chk_period')->getExpression());

        /** @var Schema $restored */
        $restored = unserialize(serialize($schema));
        $restoredTable = $restored->getTable('items');
        foreach ($table->getChecks() as $check) {
            self::assertNotNull($check->getName());
            $restoredCheck = $restoredTable->getCheck($check->getName());
            self::assertSame($check->getExpression(), $restoredCheck->getExpression());
            self::assertTrue($restoredCheck->isEnforced());
            self::assertSame($restoredTable, $restoredCheck->getTable());
        }
    }

    public function testSameNamedConstraintsInOtherSchemasDoNotLeak(): void
    {
        $otherSchema = $this->schemaName . '_other';
        $this->createSchema($otherSchema);
        $this->connection->execute(sprintf(
            'CREATE TABLE `%s`.items (price INT, CONSTRAINT chk_price CHECK (price >= 0))',
            $this->schemaName,
        ));
        $this->connection->execute(sprintf(
            'CREATE TABLE `%s`.items (price INT, CONSTRAINT chk_price CHECK (price < 100))',
            $otherSchema,
        ));
        $generator = new MySQL($this->connection);
        $table = $generator->generateSchema($this->schemaName)->getTable('items');
        $otherTable = $generator->generateSchema($otherSchema)->getTable('items');

        self::assertCount(1, iterator_to_array($table->getChecks()));
        self::assertCount(1, iterator_to_array($otherTable->getChecks()));
        self::assertStringContainsString('>= 0', $table->getCheck('chk_price')->getExpression());
        self::assertStringContainsString('< 100', $otherTable->getCheck('chk_price')->getExpression());
    }

    public function testTablesAndViewsWithoutChecksHaveKnownEmptyMetadata(): void
    {
        $this->connection->execute(sprintf('CREATE TABLE `%s`.items (price INT)', $this->schemaName));
        $this->connection->execute(sprintf('CREATE VIEW `%s`.item_view AS SELECT 1 AS price', $this->schemaName));
        $schema = (new MySQL($this->connection))->generateSchema($this->schemaName);

        foreach (['items', 'item_view'] as $name) {
            self::assertTrue($schema->getTable($name)->hasCheckMetadata());
            self::assertSame([], iterator_to_array($schema->getTable($name)->getChecks()));
        }
    }

    public function testMySqlNotEnforcedConstraintRemainsNotEnforced(): void
    {
        if ('mysql' !== $this->connection->getDriverInfo()->getDriver()) {
            $this->markTestSkipped('NOT ENFORCED is MySQL-specific');
        }
        $this->connection->execute(sprintf(
            'CREATE TABLE `%s`.items (price INT, CONSTRAINT chk_price CHECK (price >= 0) NOT ENFORCED)',
            $this->schemaName,
        ));
        $this->connection->execute(sprintf('INSERT INTO `%s`.items VALUES (-1)', $this->schemaName));
        $schema = (new MySQL($this->connection))->generateSchema($this->schemaName);

        self::assertFalse($schema->getTable('items')->getCheck('chk_price')->isEnforced());
        /** @var Schema $restored */
        $restored = unserialize(serialize($schema));
        self::assertFalse($restored->getTable('items')->getCheck('chk_price')->isEnforced());
    }

    public function testMariaDbCheckNamesAreScopedByTable(): void
    {
        if ('mariadb' !== $this->connection->getDriverInfo()->getDriver()) {
            $this->markTestSkipped('Table-scoped CHECK names are MariaDB-specific');
        }
        foreach (['items' => 'price >= 0', 'other_items' => 'price < 100'] as $name => $expression) {
            $this->connection->execute(sprintf(
                'CREATE TABLE `%s`.`%s` (price INT, CONSTRAINT chk_price CHECK (%s))',
                $this->schemaName,
                $name,
                $expression,
            ));
        }
        $schema = (new MySQL($this->connection))->generateSchema($this->schemaName);

        self::assertCount(1, iterator_to_array($schema->getTable('items')->getChecks()));
        self::assertCount(1, iterator_to_array($schema->getTable('other_items')->getChecks()));
        self::assertStringContainsString('>= 0', $schema->getTable('items')->getCheck('chk_price')->getExpression());
        self::assertStringContainsString('< 100', $schema->getTable('other_items')->getCheck('chk_price')->getExpression());
    }
}
