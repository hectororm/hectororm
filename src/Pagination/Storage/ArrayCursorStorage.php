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

namespace Hector\Pagination\Storage;

use Countable;
use DateInterval;

/**
 * In-memory storage for testing purposes.
 */
class ArrayCursorStorage implements CursorStorageInterface, Countable
{
    /** @var array<string, array<string, mixed>> */
    private array $cursors = [];

    /**
     * @inheritDoc
     */
    public function store(array $state, DateInterval|int|null $ttl = null): string
    {
        $name = bin2hex(random_bytes(16));
        $this->cursors[$name] = $state;

        return $name;
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $name): ?array
    {
        return $this->cursors[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        unset($this->cursors[$name]);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->cursors);
    }

    /**
     * Clear all stored cursors.
     */
    public function clear(): void
    {
        $this->cursors = [];
    }
}
