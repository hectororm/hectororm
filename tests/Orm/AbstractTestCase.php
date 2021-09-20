<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Orm\Tests;

use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use Hector\Orm\Tests\Fake\FakeOrm;
use Hector\Orm\Tests\Fake\Type\GeometryType;
use Hector\Schema\Generator\MySQL;
use LogicException;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected static ?FakeOrm $orm = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getOrm();
    }

    protected function getOrm(): FakeOrm
    {
        if (null !== self::$orm) {
            return self::$orm;
        }

        $connection = new Connection(
            dsn:    getenv('MYSQL_DSN') ?: throw new LogicException('Missing env variable "MYSQL_DSN" for tests'),
            logger: new Logger()
        );

        $schemaGenerator = new MySQL($connection);
        $schemaContainer = $schemaGenerator->generateSchemas('sakila');

        self::$orm = new FakeOrm($connection, $schemaContainer);
        self::$orm->getTypes()->add('geometry', new GeometryType());

        return self::$orm;
    }
}