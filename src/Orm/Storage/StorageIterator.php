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

namespace Hector\Orm\Storage;

use OutOfBoundsException;
use SeekableIterator;
use WeakReference;

class StorageIterator implements SeekableIterator
{
    private int $position = 0;

    /**
     * StorageIterator constructor.
     *
     * @param WeakReference[] $weakReferences
     */
    public function __construct(private array $weakReferences)
    {
        $this->weakReferences = array_values($this->weakReferences);
    }

    /**
     * @inheritDoc
     */
    public function seek($position): void
    {
        if (!isset($this->weakReferences[$position])) {
            throw new OutOfBoundsException();
        }

        $this->position = $position;
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @inheritDoc
     */
    public function current(): mixed
    {
        return $this->weakReferences[$this->position];
    }

    /**
     * @inheritDoc
     */
    public function key(): string|float|int|bool|null
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return isset($this->weakReferences[$this->position]);
    }
}
