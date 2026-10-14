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
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Plan;
use PDOException;
use PHPUnit\Framework\TestCase;

class CheckCompilerExecuteTest extends TestCase
{
    private function connection(string $driver): Connection
    {
        $connection = new Connection('sqlite' === $driver ? 'sqlite::memory:' : getenv('MYSQL_DSN'));
        if (false === $connection->getDriverInfo()->getCapabilities()->hasCheckConstraints()) {
            $this->markTestSkipped('The server does not enforce CHECK constraints');
        }

        return $connection;
    }

    private function executePlan(Connection $connection, Plan $plan): void
    {
        foreach ((new AutoCompiler($connection))->compile($plan) as $sql) {
            $connection->execute($sql);
        }
    }

    private function assertCheckRejects(Connection $connection, string $sql, string $constraintName): void
    {
        try {
            $connection->execute($sql);
            self::fail('The CHECK constraint did not reject invalid data');
        } catch (PDOException $exception) {
            self::assertStringContainsString($constraintName, $exception->getMessage());
        }
    }

    public static function providerDrivers(): array
    {
        return [['sqlite'], ['mysql']];
    }

    /**
     * @dataProvider providerDrivers
     */
    public function testCreateEnforcesChecksAndPreservesNullableSemantics(string $driver): void
    {
        $connection = $this->connection($driver);
        $table = 'ht_check_' . bin2hex(random_bytes(4));
        $check = $table . '_price';
        $plan = new Plan();
        $plan->create($table)->addColumn('price', 'INT', nullable: true)->addCheck($check, 'price >= 0');

        try {
            $this->executePlan($connection, $plan);
            $connection->execute(sprintf('INSERT INTO `%s` VALUES (0), (10), (NULL)', $table));
            $this->assertCheckRejects($connection, sprintf('INSERT INTO `%s` VALUES (-1)', $table), $check);
            $this->assertCheckRejects($connection, sprintf('UPDATE `%s` SET price = -1 WHERE price = 10', $table), $check);

            self::assertEquals([3], $connection->fetchColumn(sprintf('SELECT COUNT(*) FROM `%s`', $table)));
            self::assertEquals([10], $connection->fetchColumn(sprintf('SELECT MAX(price) FROM `%s`', $table)));
        } finally {
            $connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    public function testMySqlFamilyCanAddAndDropChecks(): void
    {
        $connection = $this->connection('mysql');
        $table = 'ht_check_' . bin2hex(random_bytes(4));
        $check = $table . '_price';

        try {
            $plan = new Plan();
            $plan->create($table)->addColumn('price', 'INT');
            $this->executePlan($connection, $plan);
            $connection->execute(sprintf('INSERT INTO `%s` VALUES (5)', $table));

            $plan = new Plan();
            $plan->alter($table)->addCheck($check, 'price >= 0');
            $this->executePlan($connection, $plan);
            $this->assertCheckRejects($connection, sprintf('INSERT INTO `%s` VALUES (-1)', $table), $check);

            $plan = new Plan();
            $plan->alter($table)->dropCheck($check);
            $this->executePlan($connection, $plan);
            $connection->execute(sprintf('INSERT INTO `%s` VALUES (-1)', $table));

            self::assertEquals([2], $connection->fetchColumn(sprintf('SELECT COUNT(*) FROM `%s`', $table)));
        } finally {
            $connection->execute(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }
}
