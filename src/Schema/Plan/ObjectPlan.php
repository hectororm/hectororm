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

namespace Hector\Schema\Plan;

use ArrayIterator;
use Countable;
use Hector\Schema\Plan\Compiler\CompilerInterface;
use Hector\Schema\Plan\Operation\OperationInterface;
use Hector\Schema\Schema;
use IteratorAggregate;

class ObjectPlan implements Countable, IteratorAggregate
{
    /** @var OperationInterface[] */
    private array $operations = [];

    /**
     * ObjectPlan constructor.
     *
     * @param string $name
     */
    public function __construct(
        private string $name,
    ) {
    }

    /**
     * Get the object name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add an operation.
     *
     * @param OperationInterface $operation
     *
     * @return void
     *
     * @internal
     */
    public function addOperation(OperationInterface $operation): void
    {
        $this->operations[] = $operation;
    }

    /**
     * Get a copy of all operations.
     *
     * @return OperationInterface[]
     */
    public function getArrayCopy(): array
    {
        return $this->operations;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->operations);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->operations);
    }

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->operations);
    }

    /**
     * Check if the plan has at least one operation of the given type.
     *
     * @param string $class Class or interface name
     *
     * @return bool
     */
    public function has(string $class): bool
    {
        foreach ($this->operations as $operation) {
            if ($operation instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a new plan with only the operations matching the given type.
     *
     * @param string $class Class or interface name
     *
     * @return static
     */
    public function filter(string $class): static
    {
        $filtered = new static($this->name);

        foreach ($this->operations as $operation) {
            if (false === $operation instanceof $class) {
                continue;
            }

            $filtered->addOperation($operation);
        }

        return $filtered;
    }

    /**
     * Return a new plan without the operations matching the given type.
     *
     * @param string $class Class or interface name
     *
     * @return static
     */
    public function without(string $class): static
    {
        $filtered = new static($this->name);

        foreach ($this->operations as $operation) {
            if ($operation instanceof $class) {
                continue;
            }

            $filtered->addOperation($operation);
        }

        return $filtered;
    }

    /**
     * Get SQL statements for this plan.
     *
     * When a schema is provided, the compiler may use it to introspect the database
     * and adapt the compilation strategy (e.g., table rebuild for SQLite, index existence checks).
     * Without a schema, all operations are assumed to be natively supported.
     *
     * @param CompilerInterface $compiler
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    public function getStatements(CompilerInterface $compiler, ?Schema $schema = null): iterable
    {
        return $compiler->compile($this, $schema);
    }
}
