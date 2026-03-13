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

use Countable;
use IteratorAggregate;

/**
 * @extends IteratorAggregate<int, OperationInterface>
 */
interface OperationGroupInterface extends OperationInterface, Countable, IteratorAggregate
{
    /**
     * Get the object name (always non-null for groups).
     *
     * @return string
     */
    public function getObjectName(): string;

    /**
     * Get a copy of all operations.
     *
     * @return OperationInterface[]
     */
    public function getArrayCopy(): array;

    /**
     * Is empty?
     *
     * @return bool
     */
    public function isEmpty(): bool;
}
