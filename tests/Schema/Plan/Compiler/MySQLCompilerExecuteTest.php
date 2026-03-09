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
use Hector\Schema\Generator\GeneratorInterface;
use Hector\Schema\Generator\MySQL;

/**
 * Class MySQLCompilerExecuteTest.
 *
 * Executes DDL plans against a real MySQL database.
 * Requires the MYSQL_DSN environment variable to be set.
 * Skips all tests when not available.
 */
class MySQLCompilerExecuteTest extends AbstractCompilerExecuteTestCase
{
    private static ?string $schemaName = null;

    /**
     * @inheritDoc
     */
    protected static function createConnection(): ?Connection
    {
        $dsn = getenv('MYSQL_DSN');

        if (false === $dsn || '' === $dsn) {
            return null;
        }

        // Extract schema name from DSN
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            static::$schemaName = $matches[1];
        }

        return new Connection($dsn);
    }

    /**
     * @inheritDoc
     */
    protected static function createGenerator(Connection $connection): GeneratorInterface
    {
        return new MySQL($connection);
    }

    /**
     * @inheritDoc
     */
    protected static function getSchemaName(): string
    {
        return static::$schemaName ?? 'sakila';
    }
}
