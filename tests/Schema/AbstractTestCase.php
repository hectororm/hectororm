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

namespace Hector\Schema\Tests;

use Hector\Connection\Connection;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\SchemaContainer;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    private static ?SchemaContainer $schemaContainer = null;

    /**
     * Get schema container.
     *
     * @return SchemaContainer
     * @throws SchemaException
     */
    public function getSchemaContainer(): SchemaContainer
    {
        if (null === static::$schemaContainer) {
            $mysqlGenerator = new MySQL(new Connection(getenv('MYSQL_DSN')));
            static::$schemaContainer = $mysqlGenerator->generateSchemas('sakila');
        }

        return static::$schemaContainer;
    }
}