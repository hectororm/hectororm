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
use InvalidArgumentException;
use Traversable;

abstract class OperationGroup implements OperationGroupInterface
{
    /** @var OperationInterface[] */
    private array $operations = [];

    /**
     * OperationGroup constructor.
     *
     * @param string $name
     */
    public function __construct(
        private string $name,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->name;
    }

    /**
     * Add an operation to the group.
     *
     * @param OperationInterface $operation
     *
     * @return void
     *
     * @throws InvalidArgumentException If the operation does not belong to this group.
     */
    protected function add(OperationInterface $operation): void
    {
        if ($operation->getObjectName() !== $this->name) {
            throw new InvalidArgumentException(
                'Operation object name "' . $operation->getObjectName() . '" ' .
                'does not match group object name "' . $this->name . '".'
            );
        }

        $this->operations[] = $operation;
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return $this->operations;
    }

    /**
     * @inheritDoc
     *
     * @return Traversable<int, OperationInterface>
     */
    public function getIterator(): Traversable
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
     * @inheritDoc
     */
    public function isEmpty(): bool
    {
        return [] === $this->operations;
    }
}
