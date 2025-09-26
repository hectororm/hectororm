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

namespace Hector\Schema;

use Countable;
use Generator;
use Hector\Schema\Exception\NotFoundException;
use IteratorAggregate;

/**
 * Interface SchemaContainerInterface.
 */
interface SchemaContainerInterface extends Countable, IteratorAggregate
{
    /**
     * Get schemas.
     *
     * @param string|null $connection
     *
     * @return Generator<Schema>
     */
    public function getSchemas(?string $connection = null): Generator;

    /**
     * Has schema?
     *
     * @param string $name
     * @param string|null $connection
     *
     * @return bool
     */
    public function hasSchema(string $name, ?string $connection = null): bool;

    /**
     * Get schema.
     *
     * @param string $name
     * @param string|null $connection
     *
     * @return Schema
     * @throws NotFoundException
     */
    public function getSchema(string $name, ?string $connection = null): Schema;

    /**
     * Has table?
     *
     * @param string $name
     * @param string|null $schemaName
     * @param string|null $connection
     *
     * @return bool
     * @throws NotFoundException
     */
    public function hasTable(string $name, ?string $schemaName = null, ?string $connection = null): bool;

    /**
     * Get table.
     *
     * @param string $name
     * @param string|null $schemaName
     * @param string|null $connection
     *
     * @return Table
     * @throws NotFoundException
     */
    public function getTable(string $name, ?string $schemaName = null, ?string $connection = null): Table;

    /**
     * Has column?
     *
     * @param string $name
     * @param string $tableName
     * @param string|null $schemaName
     * @param string|null $connection
     *
     * @return bool
     */
    public function hasColumn(
        string $name,
        string $tableName,
        ?string $schemaName = null,
        ?string $connection = null
    ): bool;

    /**
     * Get column.
     *
     * @param string $name
     * @param string $tableName
     * @param string|null $schemaName
     * @param string|null $connection
     *
     * @return Column
     * @throws NotFoundException
     */
    public function getColumn(
        string $name,
        string $tableName,
        ?string $schemaName = null,
        ?string $connection = null
    ): Column;
}
