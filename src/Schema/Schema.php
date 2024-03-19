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
use Countable;
use Generator;
use Hector\Schema\Exception\NotFoundException;
use IteratorAggregate;

/**
 * Class Schema.
 */
class Schema implements Countable, IteratorAggregate
{
    public function __construct(
        private string $connection,
        private string $name,
        private string $charset,
        private ?string $collation = null,
        private ?string $alias = null,
        private array $tables = [],
        private ?SchemaContainer $container = null,
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
            'connection' => $this->connection,
            'name' => $this->name,
            'alias' => $this->alias,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'tables' => $this->tables,
        ];
    }

    /**
     * PHP unserialize method.
     *
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->connection = $data['connection'];
        $this->name = $data['name'];
        $this->alias = $data['alias'] ?? null;
        $this->charset = $data['charset'];
        $this->collation = $data['collation'];
        $this->tables = $data['tables'];
        $this->container = null;

        $this->restoreInheritance();
    }

    /**
     * Restore inheritance.
     */
    private function restoreInheritance(): void
    {
        array_walk($this->tables, fn(Table $table) => $table->setSchema($this));
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->tables);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tables);
    }

    /**
     * Get connection.
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Get name.
     *
     * @param bool $quoted
     *
     * @return string
     */
    public function getName(bool $quoted = false): string
    {
        if ($quoted) {
            return sprintf('`%s`', $this->name);
        }

        return $this->name;
    }

    /**
     * Get alias.
     *
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Get charset.
     *
     * @return string
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Get collation.
     *
     * @return string|null
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }

    /**
     * Get tables.
     *
     * @param string|null $type
     *
     * @return Generator<Table>
     */
    public function getTables(?string $type = null): Generator
    {
        foreach ($this->tables as $table) {
            if (null === $type || $type === $table->getType()) {
                yield $table;
            }
        }
    }

    /**
     * Has table?
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasTable(string $name): bool
    {
        return array_key_exists($name, $this->tables);
    }

    /**
     * Get table.
     *
     * @param string $name
     *
     * @return Table
     * @throws NotFoundException
     */
    public function getTable(string $name): Table
    {
        return
            $this->tables[$name] ??
            throw new NotFoundException(sprintf('Table "%s" not found in schema "%s"', $name, $this->name));
    }

    /**
     * Get container.
     *
     * @return SchemaContainer|null
     */
    public function getContainer(): ?SchemaContainer
    {
        return $this->container;
    }

    /**
     * Set container.
     *
     * @param SchemaContainer|null $container
     */
    public function setContainer(?SchemaContainer $container): void
    {
        $this->container = $container;
    }
}