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

use ArrayIterator;
use Generator;
use Hector\Schema\Exception\NotFoundException;
use Traversable;

class SchemaContainer implements SchemaContainerInterface
{
    public function __construct(
        private array $schemas = [],
    ) {
        $this->restoreInheritance();
    }

    /**
     * PHP serialize method.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'schemas' => $this->schemas,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->schemas = $data['schemas'];

        $this->restoreInheritance();
    }

    /**
     * Restore inheritance.
     */
    private function restoreInheritance(): void
    {
        array_walk($this->schemas, fn(Schema $schema) => $schema->setContainer($this));
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->schemas);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->schemas);
    }

    /**
     * @inheritDoc
     */
    public function getSchemas(?string $connection = null): Generator
    {
        foreach ($this->schemas as $schema) {
            if (null === $connection || $schema->getConnection() === $connection) {
                yield $schema;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function hasSchema(string $name, ?string $connection = null): bool
    {
        /** @var Schema $schema */
        foreach ($this->getSchemas($connection) as $schema) {
            if ($schema->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getSchema(string $name, ?string $connection = null): Schema
    {
        /** @var Schema $schema */
        foreach ($this->getSchemas($connection) as $schema) {
            if ($schema->getName() === $name) {
                return $schema;
            }
        }

        if (null !== $connection) {
            throw new NotFoundException(sprintf('Schema "%s" not found in connection %s', $name, $connection));
        }

        throw new NotFoundException(sprintf('Schema "%s" not found', $name));
    }

    /**
     * @inheritDoc
     */
    public function hasTable(string $name, ?string $schemaName = null, ?string $connection = null): bool
    {
        if (null !== $schemaName) {
            return $this->getSchema($schemaName, $connection)->hasTable($name);
        }

        foreach ($this->getSchemas($connection) as $schema) {
            if ($schema->hasTable($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getTable(string $name, ?string $schemaName = null, ?string $connection = null): Table
    {
        if (null !== $schemaName) {
            return $this->getSchema($schemaName, $connection)->getTable($name);
        }

        /** @var Schema $schema */
        foreach ($this->getSchemas($connection) as $schema) {
            foreach ($schema->getTables() as $table) {
                if ($table->getName() === $name) {
                    return $table;
                }
            }
        }

        throw new NotFoundException(sprintf('Table "%s" not found in schemas', $name));
    }

    /**
     * @inheritDoc
     */
    public function hasColumn(
        string $name,
        string $tableName,
        ?string $schemaName = null,
        ?string $connection = null
    ): bool {
        if (false === $this->hasTable($tableName, $schemaName, $connection)) {
            return false;
        }

        $table = $this->getTable($tableName, $schemaName, $connection);

        return $table->hasColumn($name);
    }

    /**
     * @inheritDoc
     */
    public function getColumn(
        string $name,
        string $tableName,
        ?string $schemaName = null,
        ?string $connection = null
    ): Column {
        $table = $this->getTable($tableName, $schemaName, $connection);

        return $table->getColumn($name);
    }
}