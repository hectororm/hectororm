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

declare(strict_types=1);

namespace Hector\Orm;

use Hector\Connection\Connection;
use Hector\Connection\ConnectionSet;
use Hector\Connection\Log\Logger;
use Hector\Orm\Exception\OrmException;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Generator\MySQL;
use Hector\Schema\SchemaContainer;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class OrmFactory
{
    public const CACHE_ORM_KEY = 'HECTOR_ORM';

    /**
     * Orm.
     *
     * @param array $options
     * @param Connection|ConnectionSet|null $connection
     * @param CacheInterface|null $cache
     *
     * @return Orm
     * @throws InvalidArgumentException
     * @throws OrmException
     * @throws SchemaException
     */
    public static function orm(array $options, Connection|ConnectionSet|null $connection, ?CacheInterface $cache = null): Orm
    {
        if (null === $connection) {
            $connection = static::connection($options);
        }

        if (null === $cache || !$cache->has(static::CACHE_ORM_KEY)) {
            if (!array_key_exists('schemas', $options) || !is_array($options['schemas'])) {
                throw new OrmException('Missing "schemas" (array) option key in configuration');
            }

            $schemaContainer = static::schemaContainer($connection, ...$options['schemas']);
            $orm = new Orm($connection, $schemaContainer);

            if (null !== $cache) {
                $cache->set(static::CACHE_ORM_KEY, $orm);
            }

            return $orm;
        }

        return $cache->get(static::CACHE_ORM_KEY);
    }

    /**
     * Connection.
     *
     * @param array $options
     *
     * @return Connection
     * @throws OrmException
     */
    public static function connection(array $options): Connection
    {
        if (!array_key_exists('dsn', $options)) {
            throw new OrmException('Missing "dsn" option key in configuration');
        }

        return new Connection(
            dsn: $options['dsn'],
            readDsn: $options['read_dsn'] ?? null,
            name: $options['name'] ?? Connection::DEFAULT_NAME,
            logger: ($options['log'] ?? false) ? new Logger() : null
        );
    }

    /**
     * Schema container.
     *
     * @param Connection $connection
     * @param string ...$schemas
     *
     * @return SchemaContainer
     * @throws SchemaException
     */
    public static function schemaContainer(Connection $connection, string ...$schemas): SchemaContainer
    {
        $generator = new MySQL($connection);

        return $generator->generateSchemas(...$schemas);
    }
}