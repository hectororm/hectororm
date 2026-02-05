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

use DateInterval;

interface CursorStorageInterface
{
    /**
     * Store cursor state.
     *
     * @param array<string, mixed> $state
     *
     * @return string Generated cursor name
     */
    public function store(array $state, DateInterval|int|null $ttl = null): string;

    /**
     * Retrieve cursor state by name.
     *
     * @return array<string, mixed>|null
     */
    public function retrieve(string $name): ?array;

    /**
     * Delete cursor by name.
     */
    public function delete(string $name): void;
}
